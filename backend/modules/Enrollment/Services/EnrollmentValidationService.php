<?php

namespace Modules\Enrollment\Services;

use Illuminate\Validation\ValidationException;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Curriculum\Models\CreditLimit;
use Modules\Curriculum\Models\Curriculum;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Schedule\Services\ScheduleConflictService;
use Modules\Settings\Services\SettingService;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

class EnrollmentValidationService
{
    public function __construct(
        protected ScheduleConflictService $conflictService,
        protected AcademicHistoryProvider $historyProvider,
        protected SettingService $settingService
    ) {}

    /**
     * Run all validation rules before adding a class to an enrollment (KRS).
     *
     * @throws ValidationException
     */
    public function validateClassAddition(
        StudentEnrollment $enrollment,
        AcademicClass $class,
        bool $bypassCurriculum = false
    ): void {
        $errors = $this->checkClassAddition($enrollment, $class, $bypassCurriculum);

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Collect every reason why a class cannot be added to an enrollment (KRS).
     *
     * Returns an empty array when the class is eligible. This is the same rule
     * set used by validateClassAddition(), but instead of throwing on the first
     * failure it reports all of them, so the UI can explain *why* a class is
     * not selectable before the student even submits it.
     *
     * @return array<string, list<string>> Validation errors keyed by field name.
     */
    public function checkClassAddition(
        StudentEnrollment $enrollment,
        AcademicClass $class,
        bool $bypassCurriculum = false
    ): array {
        $student = $enrollment->student;

        // 1. Student Status Rule — nothing else matters if the student is inactive.
        if ($student->status !== StudentStatus::ACTIVE) {
            return [
                'student_id' => ["Mahasiswa berstatus tidak aktif (status: {$student->status->value}). Hanya mahasiswa aktif yang dapat mengambil KRS."],
            ];
        }

        // 1b. KRS Schedule & Deadline Window Rule
        $windowErrors = $this->checkKrsWindow($enrollment);
        if (!empty($windowErrors)) {
            return $windowErrors;
        }

        // 2. Class Availability & Status Rule — a closed/foreign class cannot be taken at all.
        $availabilityErrors = $this->checkClassAvailability($enrollment, $class);
        if (!empty($availabilityErrors)) {
            return $availabilityErrors;
        }

        $errors = [];

        // 3. Capacity Rule
        if ($class->enrolled_count >= $class->capacity) {
            $errors['class_id'][] = "Kapasitas kelas {$class->code} sudah penuh ({$class->capacity}/{$class->capacity} mahasiswa).";
        }

        // 4. Duplicate Class / Duplicate Course in Same Enrollment Rule
        $existingCourseIds = $enrollment->items()
            ->where('status', 'enrolled')
            ->pluck('course_id')
            ->toArray();

        if (in_array($class->course_id, $existingCourseIds, true)) {
            $courseName = $class->course?->name ?? "Mata Kuliah #{$class->course_id}";
            $errors['course_id'][] = "Mata kuliah {$courseName} sudah terdaftar di dalam KRS Anda.";
        }

        // 5. Prerequisite Rule
        if (!$this->historyProvider->hasPassedPrerequisites($student, $class->course_id, $enrollment->semester_id)) {
            $course = $class->course()->with('prerequisites')->first();
            $prereqNames = $course->prerequisites->pluck('name')->implode(', ');
            $errors['prerequisite'][] = "Anda belum memenuhi prasyarat untuk mata kuliah {$course->name}: {$prereqNames}.";
        }

        // 6. Schedule Conflict Rule
        try {
            $this->conflictService->validateStudentScheduleConflict($enrollment, $class);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $errors[$key][] = $message;
                }
            }
        }

        // 7. Max SKS Limit Rule — the ceiling is per student (IPS tier from the
        //    curriculum's credit_limits), not one global number for everybody.
        $maxSks = $this->getMaxCredits($student, $enrollment);
        $courseCredits = (int) ($class->course?->credits ?? 2);
        $currentCredits = (int) $enrollment->items()->where('status', 'enrolled')->sum('credits');

        if (($currentCredits + $courseCredits) > $maxSks) {
            $errors['credits'][] = "Melebihi batas maksimal ({$maxSks} SKS). SKS saat ini: {$currentCredits} SKS, mencoba menambah: {$courseCredits} SKS.";
        }

        // 8. Curriculum & Study Program Match Rule
        foreach ($this->checkCurriculumMatch($student, $class, $bypassCurriculum) as $key => $messages) {
            foreach ($messages as $message) {
                $errors[$key][] = $message;
            }
        }

        return $errors;
    }

    /**
     * Resolve the SKS ceiling that applies to ONE student.
     *
     * Priority:
     *  a. `student_enrollments.max_credits` — the quota recorded on this KRS. It is
     *     written from the IPS tier at creation time (CreateEnrollmentAction) and
     *     can be raised/lowered by the academic office through the advisor-quota
     *     endpoint, so whenever it holds a positive value it is authoritative.
     *  b. The IPS tier of `credit_limits.rules` attached to the student's applicable
     *     curriculum, matched against the student's previous-semester IPS computed
     *     from real grade data.
     *  c. The global `max_sks` setting.
     *
     * When the student has no prior IPS yet (mahasiswa baru / semester pertama)
     * the highest configured tier is used, because there is no low performance to
     * restrict.
     */
    public function getMaxCredits(Student $student, ?StudentEnrollment $enrollment = null): int
    {
        // (a) per-enrollment quota
        $explicitMaxCredits = $enrollment ? (int) $enrollment->max_credits : 0;
        if ($explicitMaxCredits > 0) {
            return $explicitMaxCredits;
        }

        // (b) IPS tier from credit_limits.rules
        $rules = $this->creditLimitRules($student);

        if (!empty($rules)) {
            $ips = $this->historyProvider->calculatePreviousIps($student, $enrollment?->semester_id);

            if ($ips === null) {
                $highest = $this->highestTierCredits($rules);

                return $highest ?? $this->globalMaxSks();
            }

            $tier = $this->tierForIps($rules, $ips);

            if ($tier !== null) {
                return $tier;
            }
        }

        // (c) global fallback
        return $this->globalMaxSks();
    }

    /**
     * Global `max_sks` setting (last-resort ceiling).
     */
    public function globalMaxSks(): int
    {
        return (int) $this->settingService->get('max_sks', 24);
    }

    /**
     * Read the tier table `[{min_gpa, max_gpa, max_sks}, ...]` of the credit limit
     * bound to the student's applicable curriculum, sorted from the highest GPA down.
     *
     * @return array<int, array{min_gpa: float, max_gpa: float, max_sks: int}>
     */
    public function creditLimitRules(Student $student): array
    {
        $curriculum = $this->applicableCurriculum($student);

        if (!$curriculum?->credit_limit_id) {
            return [];
        }

        $creditLimit = CreditLimit::find($curriculum->credit_limit_id);
        $rawRules = is_array($creditLimit?->rules) ? $creditLimit->rules : [];

        $rules = [];
        foreach ($rawRules as $rule) {
            if (!is_array($rule) || !isset($rule['max_sks'])) {
                continue;
            }

            $rules[] = [
                'min_gpa' => (float) ($rule['min_gpa'] ?? 0),
                'max_gpa' => (float) ($rule['max_gpa'] ?? 4.0),
                'max_sks' => (int) $rule['max_sks'],
            ];
        }

        usort($rules, fn (array $a, array $b) => $b['min_gpa'] <=> $a['min_gpa']);

        return $rules;
    }

    /**
     * The curriculum that governs this student: the active one of their study
     * program, or — for older cohorts — the newest one that had already started
     * when they were admitted.
     */
    protected function applicableCurriculum(Student $student): ?Curriculum
    {
        if (!$student->study_program_id) {
            return null;
        }

        $curriculum = Curriculum::where('study_program_id', $student->study_program_id)
            ->where('status', 'active')
            ->orderByDesc('start_year')
            ->first();

        if ($curriculum) {
            return $curriculum;
        }

        return Curriculum::where('study_program_id', $student->study_program_id)
            ->when($student->admission_year, fn ($q) => $q->where('start_year', '<=', $student->admission_year))
            ->orderByDesc('start_year')
            ->first();
    }

    /**
     * The max_sks of the tier that covers the given IPS.
     *
     * Tiers are scanned from the highest min_gpa downwards and the first one the
     * IPS reaches wins, which also honours max_gpa for well-formed configurations.
     * An IPS below every tier falls into the most restrictive one.
     */
    protected function tierForIps(array $rules, float $ips): ?int
    {
        foreach ($rules as $rule) {
            // Small epsilon: tiers are usually written as 2.50 - 2.99 while an IPS
            // of 2.995 must still land in that band rather than fall through.
            if ($ips >= ($rule['min_gpa'] - 0.005)) {
                return (int) $rule['max_sks'];
            }
        }

        $lowest = end($rules);

        return $lowest ? (int) $lowest['max_sks'] : null;
    }

    /**
     * The most generous tier — used when the student has no IPS history yet.
     */
    protected function highestTierCredits(array $rules): ?int
    {
        if (empty($rules)) {
            return null;
        }

        return (int) max(array_map(fn (array $rule) => (int) $rule['max_sks'], $rules));
    }

    /**
     * Check the KRS submission window (open date / deadline / KPRS revision window).
     *
     * Public because the window must be re-validated on every mutation of a KRS,
     * not only when a class is added: SubmitEnrollmentAction and
     * RemoveEnrollmentItemAction call it directly instead of duplicating date logic.
     *
     * @return array<string, list<string>>
     */
    public function checkKrsWindow(StudentEnrollment $enrollment): array
    {
        $semester = $enrollment->semester;

        if (!$semester) {
            return [];
        }

        $today = now()->startOfDay();

        if ($semester->krs_start_date && $today->lt($semester->krs_start_date->startOfDay())) {
            $startDate = $semester->krs_start_date->format('d/m/Y');

            return [
                'krs' => ["Pengisian KRS belum dibuka. Jadwal KRS dimulai pada tanggal {$startDate}."],
            ];
        }

        if ($semester->krs_end_date && $today->gt($semester->krs_end_date->endOfDay())) {
            // Check if in KPRS (perubahan KRS) window
            $inKprs = false;
            if ($semester->kprs_start_date && $semester->kprs_end_date) {
                $inKprs = $today->gte($semester->kprs_start_date->startOfDay()) && $today->lte($semester->kprs_end_date->endOfDay());
            }

            if (!$inKprs) {
                $endDate = $semester->krs_end_date->format('d/m/Y');

                return [
                    'krs' => ["Batas waktu (deadline) pengisian KRS semester ini telah berakhir pada tanggal {$endDate}."],
                ];
            }
        }

        return [];
    }

    /**
     * Check whether the class is open and belongs to the enrollment semester.
     *
     * @return array<string, list<string>>
     */
    protected function checkClassAvailability(StudentEnrollment $enrollment, AcademicClass $class): array
    {
        if ($class->status !== ClassStatus::OPEN) {
            return [
                'class_id' => ["Kelas {$class->code} ({$class->name}) belum dibuka (status: {$class->status->value}). Silakan ubah status kelas menjadi 'Buka (Open)' di menu Perkuliahan."],
            ];
        }

        if ($class->semester_id !== $enrollment->semester_id) {
            return [
                'class_id' => ['Kelas ini tidak terdaftar pada semester akademik yang aktif.'],
            ];
        }

        return [];
    }

    /**
     * Check that the course is part of the student's study program curriculum.
     *
     * @return array<string, list<string>>
     */
    protected function checkCurriculumMatch(Student $student, AcademicClass $class, bool $bypassCurriculum): array
    {
        if ($bypassCurriculum || !$student->study_program_id) {
            return [];
        }

        $activeCurriculum = Curriculum::where('study_program_id', $student->study_program_id)
            ->where('status', 'active')
            ->first();

        if ($activeCurriculum) {
            // Jika ada kurikulum aktif: cek apakah MK ada di kurikulum
            $curriculumCourseIds = $activeCurriculum->subjects()->pluck('course_id')->toArray();
            if (!empty($curriculumCourseIds) && !in_array($class->course_id, $curriculumCourseIds, true)) {
                $courseName = $class->course?->name ?? "Mata Kuliah #{$class->course_id}";

                return [
                    'curriculum' => [
                        "Mata kuliah {$courseName} tidak termasuk dalam kurikulum aktif program studi Anda. "
                        . "Hubungi Dosen PA atau Admin untuk mendapatkan izin mengambil mata kuliah di luar kurikulum."
                    ],
                ];
            }

            return [];
        }

        // Fallback jika belum ada kurikulum: cek study_program_id kelas vs prodi mahasiswa
        // Kelas boleh dari: prodi mahasiswa sendiri, ATAU prodi null (MK umum/universal)
        $classStudyProgramId = $class->study_program_id ?? null;
        if ($classStudyProgramId !== null && $classStudyProgramId !== $student->study_program_id) {
            $courseName = $class->course?->name ?? "Mata Kuliah #{$class->course_id}";
            $classProgram = $class->studyProgram?->name ?? "prodi lain";

            return [
                'study_program' => [
                    "Mata kuliah {$courseName} adalah milik {$classProgram}, bukan program studi Anda. "
                    . "Hubungi Dosen PA atau Admin untuk mendapatkan izin mengambil mata kuliah lintas prodi."
                ],
            ];
        }

        return [];
    }
}
