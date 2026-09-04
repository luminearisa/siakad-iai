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
        $student = $enrollment->student;

        // 1. Student Status Rule
        if ($student->status !== StudentStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'student_id' => ["Mahasiswa berstatus tidak aktif (status: {$student->status->value}). Hanya mahasiswa aktif yang dapat mengambil KRS."],
            ]);
        }

        // 1b. KRS Schedule & Deadline Window Rule
        $semester = $enrollment->semester;
        if ($semester) {
            $today = now()->startOfDay();
            if ($semester->krs_start_date && $today->lt($semester->krs_start_date->startOfDay())) {
                $startDate = $semester->krs_start_date->format('d/m/Y');
                throw ValidationException::withMessages([
                    'krs' => ["Pengisian KRS belum dibuka. Jadwal KRS dimulai pada tanggal {$startDate}."],
                ]);
            }

            if ($semester->krs_end_date && $today->gt($semester->krs_end_date->endOfDay())) {
                // Check if in KPRS (perubahan KRS) window
                $inKprs = false;
                if ($semester->kprs_start_date && $semester->kprs_end_date) {
                    $inKprs = $today->gte($semester->kprs_start_date->startOfDay()) && $today->lte($semester->kprs_end_date->endOfDay());
                }

                if (!$inKprs) {
                    $endDate = $semester->krs_end_date->format('d/m/Y');
                    throw ValidationException::withMessages([
                        'krs' => ["Batas waktu (deadline) pengisian KRS semester ini telah berakhir pada tanggal {$endDate}."],
                    ]);
                }
            }
        }

        // 2. Class Availability & Status Rule
        if ($class->status !== ClassStatus::OPEN) {
            throw ValidationException::withMessages([
                'class_id' => ["Kelas {$class->code} ({$class->name}) belum dibuka (status: {$class->status->value}). Silakan ubah status kelas menjadi 'Buka (Open)' di menu Perkuliahan."],
            ]);
        }

        if ($class->semester_id !== $enrollment->semester_id) {
            throw ValidationException::withMessages([
                'class_id' => ['Kelas ini tidak terdaftar pada semester akademik yang aktif.'],
            ]);
        }

        // 3. Capacity Rule
        if ($class->enrolled_count >= $class->capacity) {
            throw ValidationException::withMessages([
                'class_id' => ["Kapasitas kelas {$class->code} sudah penuh ({$class->capacity}/{$class->capacity} mahasiswa)."],
            ]);
        }

        // 4. Duplicate Class / Duplicate Course in Same Enrollment Rule
        $existingCourseIds = $enrollment->items()
            ->where('status', 'enrolled')
            ->pluck('course_id')
            ->toArray();

        if (in_array($class->course_id, $existingCourseIds, true)) {
            $courseName = $class->course?->name ?? "Mata Kuliah #{$class->course_id}";
            throw ValidationException::withMessages([
                'course_id' => ["Mata kuliah {$courseName} sudah terdaftar di dalam KRS Anda."],
            ]);
        }

        // 5. Prerequisite Rule
        if (!$this->historyProvider->hasPassedPrerequisites($student, $class->course_id, $enrollment->semester_id)) {
            $course = $class->course()->with('prerequisites')->first();
            $prereqNames = $course->prerequisites->pluck('name')->implode(', ');
            throw ValidationException::withMessages([
                'prerequisite' => ["Anda belum memenuhi prasyarat untuk mata kuliah {$course->name}: {$prereqNames}."],
            ]);
        }

        // 6. Schedule Conflict Rule
        $this->conflictService->validateStudentScheduleConflict($enrollment, $class);

        // 7. Max SKS Limit Rule
        $maxSks = (int) $this->settingService->get('max_sks', 24);
        $courseCredits = (int) ($class->course?->credits ?? 2);
        $currentCredits = (int) $enrollment->items()->where('status', 'enrolled')->sum('credits');

        if (($currentCredits + $courseCredits) > $maxSks) {
            throw ValidationException::withMessages([
                'credits' => ["Melebihi batas maksimal ({$maxSks} SKS). SKS saat ini: {$currentCredits} SKS, mencoba menambah: {$courseCredits} SKS."],
            ]);
        }

        // 8. Curriculum & Study Program Match Rule
        if (!$bypassCurriculum && $student->study_program_id) {
            $activeCurriculum = Curriculum::where('study_program_id', $student->study_program_id)
                ->where('status', 'active')
                ->first();

            if ($activeCurriculum) {
                // Jika ada kurikulum aktif: cek apakah MK ada di kurikulum
                $curriculumCourseIds = $activeCurriculum->subjects()->pluck('course_id')->toArray();
                if (!empty($curriculumCourseIds) && !in_array($class->course_id, $curriculumCourseIds, true)) {
                    $courseName = $class->course?->name ?? "Mata Kuliah #{$class->course_id}";
                    throw ValidationException::withMessages([
                        'curriculum' => [
                            "Mata kuliah {$courseName} tidak termasuk dalam kurikulum aktif program studi Anda. "
                            . "Hubungi Dosen PA atau Admin untuk mendapatkan izin mengambil mata kuliah di luar kurikulum."
                        ],
                    ]);
                }
            } else {
                // Fallback jika belum ada kurikulum: cek study_program_id kelas vs prodi mahasiswa
                // Kelas boleh dari: prodi mahasiswa sendiri, ATAU prodi null (MK umum/universal)
                $classStudyProgramId = $class->study_program_id ?? null;
                if ($classStudyProgramId !== null && $classStudyProgramId !== $student->study_program_id) {
                    $courseName = $class->course?->name ?? "Mata Kuliah #{$class->course_id}";
                    $classProgram = $class->studyProgram?->name ?? "prodi lain";
                    throw ValidationException::withMessages([
                        'study_program' => [
                            "Mata kuliah {$courseName} adalah milik {$classProgram}, bukan program studi Anda. "
                            . "Hubungi Dosen PA atau Admin untuk mendapatkan izin mengambil mata kuliah lintas prodi."
                        ],
                    ]);
                }
            }
        }
    }
}
