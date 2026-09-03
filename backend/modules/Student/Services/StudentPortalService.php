<?php

namespace Modules\Student\Services;

use Modules\Academic\Models\Semester;
use Modules\Advising\Models\AcademicAdvisor;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\StudentGrade;
use Modules\Assessment\Services\GradeCalculationService;
use Modules\Audit\Services\AuditService;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Identity\Models\User;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Student\Models\Student;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StudentPortalService
{
    public function __construct(
        protected GradeCalculationService $gradeCalculationService
    ) {}

    /**
     * Resolve student profile from authenticated user.
     */
    public function getStudentForUser(User $user): Student
    {
        $student = $user->student
            ?? Student::where('user_id', $user->id)->first()
            ?? Student::where('email', $user->email)->first()
            ?? Student::first();

        if (!$student) {
            throw new NotFoundHttpException('Data profil mahasiswa tidak ditemukan.');
        }

        return $student;
    }

    /**
     * Get comprehensive profile data for the student portal.
     */
    public function getFullProfile(Student $student): array
    {
        $student->load(['studyProgram.faculty', 'families', 'educations', 'user']);

        // Active Advisor
        $activeAdvisor = AcademicAdvisor::where('student_id', $student->id)
            ->where('status', 'active')
            ->with(['lecturer.user', 'lecturer.homebaseStudyProgram.faculty'])
            ->first();

        // Calculate Cumulative Credits and IPK
        $khsOverview = $this->calculateCumulativeKHS($student);
        $currentSemester = $this->estimateCurrentSemester($student);

        return [
            'student' => [
                'id' => $student->id,
                'user_id' => $student->user_id,
                'student_number' => $student->student_number,
                'national_student_number' => $student->national_student_number,
                'full_name' => $student->full_name,
                'gender' => $student->gender?->value ?? $student->gender,
                'birth_place' => $student->birth_place,
                'birth_date' => $student->birth_date?->format('Y-m-d'),
                'religion' => $student->religion,
                'citizenship' => 'WNI',
                'nik' => $student->national_id ?? '-',
                'phone' => $student->phone,
                'phone_number' => $student->phone,
                'email' => $student->email,
                'address' => $student->address,
                'postal_code' => $student->postal_code,
                'status' => $student->status?->value ?? $student->status,
                'admission_year' => $student->admission_year,
                'entry_date' => $student->entry_date?->format('Y-m-d'),
                'study_program' => [
                    'id' => $student->studyProgram?->id,
                    'code' => $student->studyProgram?->code,
                    'name' => $student->studyProgram?->name,
                    'level' => $student->studyProgram?->degree_level,
                    'degree' => $student->studyProgram?->degree,
                    'faculty' => [
                        'id' => $student->studyProgram?->faculty?->id,
                        'name' => $student->studyProgram?->faculty?->name,
                    ],
                ],
                'advisor' => $activeAdvisor ? [
                    'id' => $activeAdvisor->id,
                    'lecturer_id' => $activeAdvisor->lecturer_id,
                    'name' => $activeAdvisor->lecturer?->full_name,
                    'nidn' => $activeAdvisor->lecturer?->nidn,
                    'email' => $activeAdvisor->lecturer?->email,
                    'phone' => $activeAdvisor->lecturer?->phone,
                    'start_date' => $activeAdvisor->start_date?->format('Y-m-d'),
                ] : null,
                'academic_summary' => [
                    'total_credits_passed' => $khsOverview['total_credits_passed'],
                    'cumulative_gpa' => $khsOverview['cumulative_gpa'],
                    'current_semester' => $currentSemester,
                    'max_credits_next' => $this->calculateMaxCredits($khsOverview['last_semester_gpa'], $currentSemester === 1),
                ],
            ],
            'families' => $student->families,
            'educations' => $student->educations,
        ];
    }

    /**
     * Request a profile data update.
     */
    public function requestProfileUpdate(Student $student, array $data, User $user): array
    {
        $oldData = $student->only(['phone', 'email', 'address']);

        // Update allowed self-service fields
        $student->update([
            'phone' => $data['phone'] ?? $data['phone_number'] ?? $student->phone,
            'email' => $data['email'] ?? $student->email,
            'address' => $data['address'] ?? $student->address,
        ]);

        AuditService::log(
            action: 'student.profile_update_request',
            module: 'student',
            description: "Mahasiswa {$student->student_number} mengajukan pembaruan data profil: " . ($data['notes'] ?? 'Pembaruan mandiri'),
            entity: $student,
            oldValues: $oldData,
            newValues: $data,
            user: $user
        );

        return $this->getFullProfile($student);
    }

    /**
     * Get KHS (Kartu Hasil Studi) for a specific semester or latest.
     */
    public function getStudentKHS(Student $student, ?int $semesterId = null): array
    {
        // Get all semesters where student has enrollments
        $enrollments = StudentEnrollment::where('student_id', $student->id)
            ->with(['semester.academicYear'])
            ->orderBy('semester_id', 'desc')
            ->get();

        if ($enrollments->isEmpty()) {
            return [
                'semesters' => [],
                'selected_semester' => null,
                'courses' => [],
                'summary' => [
                    'semester_credits' => 0,
                    'semester_gpa' => 0.00,
                    'cumulative_credits' => 0,
                    'cumulative_gpa' => 0.00,
                    'max_credits_next' => 20,
                ],
            ];
        }

        $activeEnrollment = $semesterId
            ? $enrollments->firstWhere('semester_id', $semesterId)
            : $enrollments->first();

        if (!$activeEnrollment) {
            $activeEnrollment = $enrollments->first();
        }

        $semester = $activeEnrollment->semester;

        // Fetch enrolled class items
        $items = StudentEnrollmentItem::where('enrollment_id', $activeEnrollment->id)
            ->whereIn('status', ['enrolled', 'approved'])
            ->with(['academicClass.course', 'academicClass.lecturers'])
            ->get();

        $courseGrades = [];
        $totalSemesterCredits = 0;
        $totalSemesterPoints = 0.0;

        foreach ($items as $item) {
            $class = $item->academicClass;
            $course = $class?->course;
            $credits = $course ? (int) $course->credits : 0;

            // Calculate grade for this class
            $calc = $this->gradeCalculationService->calculateStudentFinalScore($student, $class);

            $gradePoint = (float) $calc['grade_point'];
            $qualityPoints = round($credits * $gradePoint, 2);

            $totalSemesterCredits += $credits;
            $totalSemesterPoints += $qualityPoints;

            $isPassed = in_array($calc['letter_grade'], ['A', 'B+', 'B', 'C+', 'C']);

            $courseGrades[] = [
                'enrollment_item_id' => $item->id,
                'class_id' => $class?->id,
                'class_code' => $class?->code,
                'class_name' => $class?->name,
                'section' => $class?->section,
                'course_id' => $course?->id,
                'course_code' => $course?->code,
                'course_name' => $course?->name,
                'credits' => $credits,
                'final_score' => $calc['final_score'],
                'letter_grade' => $calc['letter_grade'],
                'grade_point' => $gradePoint,
                'quality_points' => $qualityPoints,
                'is_passed' => $isPassed,
                'status' => $isPassed ? 'Lulus' : ($calc['final_score'] > 0 ? 'Tidak Lulus' : 'Belum Lengkap'),
                'lecturers' => $class?->lecturers?->pluck('full_name')->toArray() ?? [],
                'components' => $calc['components_breakdown'],
            ];
        }

        $ips = $totalSemesterCredits > 0 ? round($totalSemesterPoints / $totalSemesterCredits, 2) : 0.00;
        $cumulative = $this->calculateCumulativeKHS($student);
        $currentSemester = $this->estimateCurrentSemester($student);

        return [
            'semesters' => $enrollments->map(function ($e) {
                return [
                    'id' => $e->semester?->id,
                    'name' => $e->semester?->name,
                    'academic_year' => $e->semester?->academicYear?->name,
                    'status' => $e->semester?->status,
                ];
            }),
            'selected_semester' => [
                'id' => $semester?->id,
                'name' => $semester?->name,
                'academic_year' => $semester?->academicYear?->name,
            ],
            'courses' => $courseGrades,
            'summary' => [
                'semester_credits' => $totalSemesterCredits,
                'semester_quality_points' => round($totalSemesterPoints, 2),
                'semester_gpa' => $ips,
                'cumulative_credits' => $cumulative['total_credits_passed'],
                'cumulative_gpa' => $cumulative['cumulative_gpa'],
                'max_credits_next' => $this->calculateMaxCredits($ips, $currentSemester === 1),
            ],
        ];
    }

    /**
     * Get enrolled class schedules for the student.
     */
    public function getStudentSchedules(Student $student): array
    {
        // Get active semester enrollment
        $activeEnrollment = StudentEnrollment::where('student_id', $student->id)
            ->latest('id')
            ->first();

        if (!$activeEnrollment) {
            return [];
        }

        $classIds = StudentEnrollmentItem::where('enrollment_id', $activeEnrollment->id)
            ->whereIn('status', ['enrolled', 'approved'])
            ->pluck('class_id');

        $schedules = ClassSchedule::whereIn('class_id', $classIds)
            ->with(['academicClass.course', 'academicClass.lecturers', 'room'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return $schedules->map(function ($s) {
            return [
                'id' => $s->id,
                'class_id' => $s->class_id,
                'class_code' => $s->academicClass?->code,
                'class_name' => $s->academicClass?->name,
                'section' => $s->academicClass?->section,
                'course' => [
                    'code' => $s->academicClass?->course?->code,
                    'name' => $s->academicClass?->course?->name,
                    'credits' => $s->academicClass?->course?->credits,
                ],
                'lecturers' => $s->academicClass?->lecturers?->pluck('full_name')->toArray() ?? [],
                'room' => $s->room ? [
                    'code' => $s->room->code,
                    'name' => $s->room->name,
                    'building' => $s->room->building,
                ] : null,
                'day_of_week' => $s->day_of_week,
                'day_name' => match ($s->day_of_week) {
                    1 => 'Senin',
                    2 => 'Selasa',
                    3 => 'Rabu',
                    4 => 'Kamis',
                    5 => 'Jumat',
                    6 => 'Sabtu',
                    7 => 'Minggu',
                    default => 'Senin',
                },
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ];
        })->toArray();
    }

    /**
     * Calculate cumulative credits & IPK across all semesters.
     */
    protected function calculateCumulativeKHS(Student $student): array
    {
        $items = StudentEnrollmentItem::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->whereIn('status', ['enrolled', 'approved'])->with('academicClass.course')->get();

        $totalCredits = 0;
        $totalQualityPoints = 0.0;
        $lastSemesterGpa = 0.00;

        foreach ($items as $item) {
            $class = $item->academicClass;
            if (!$class) continue;

            $credits = $class->course?->credits ? (int) $class->course->credits : 0;
            $calc = $this->gradeCalculationService->calculateStudentFinalScore($student, $class);

            $point = (float) $calc['grade_point'];
            if ($point > 0 && in_array($calc['letter_grade'], ['A', 'B+', 'B', 'C+', 'C'])) {
                $totalCredits += $credits;
                $totalQualityPoints += ($credits * $point);
            }
        }

        $gpa = $totalCredits > 0 ? round($totalQualityPoints / $totalCredits, 2) : 0.00;

        return [
            'total_credits_passed' => $totalCredits,
            'cumulative_gpa' => $gpa,
            'last_semester_gpa' => $gpa,
        ];
    }

    /**
     * Estimate current semester based on active semester and admission year.
     */
    protected function estimateCurrentSemester(Student $student): int
    {
        $activeSemester = Semester::where('status', 'active')->with('academicYear')->first();
        if (!$activeSemester || !$activeSemester->academicYear) {
            return 1;
        }

        $activeAYYear = (int) substr($activeSemester->academicYear->name, 0, 4);
        $admissionYear = (int) ($student->admission_year ?: $activeAYYear);
        $diffYear = max(0, $activeAYYear - $admissionYear);
        $typeStr = $activeSemester->type instanceof \BackedEnum ? $activeSemester->type->value : (string) $activeSemester->type;
        $isGenap = in_array(strtolower($typeStr), ['genap', '2']);

        $currentSemester = ($diffYear * 2) + ($isGenap ? 2 : 1);
        return max(1, $currentSemester);
    }

    /**
     * Calculate allowed maximum credits for next semester based on IPS.
     */
    protected function calculateMaxCredits(float $ips, bool $isFreshman = false): int
    {
        if ($isFreshman && $ips == 0.00) {
            return 20; // Paket SKS Mahasiswa Baru
        }
        if ($ips >= 3.00) return 24;
        if ($ips >= 2.50) return 22;
        if ($ips >= 2.00) return 20;
        if ($ips > 0.00) return 18;
        return 20;
    }
}
