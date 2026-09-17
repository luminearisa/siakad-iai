<?php

namespace Modules\Enrollment\Services;

use Illuminate\Validation\ValidationException;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
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

        // 7. Max SKS Limit Rule
        $maxSks = (int) $this->settingService->get('max_sks', 24);
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
     * Check the KRS submission window (open date / deadline / KPRS revision window).
     *
     * @return array<string, list<string>>
     */
    protected function checkKrsWindow(StudentEnrollment $enrollment): array
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
