<?php

namespace Modules\Assessment\Services;

use Illuminate\Support\Facades\DB;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\StudentGrade;
use Modules\Audit\Services\AuditService;
use Modules\Class\Models\AcademicClass;
use Modules\Student\Models\Student;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GradeFinalizationService
{
    public function __construct(
        protected GradeValidationService $validationService,
        protected GradeCalculationService $calculationService,
        protected AuditService $auditService
    ) {}

    /**
     * Finalize all grades for an academic class.
     * Transaction-safe with row locking.
     */
    public function finalizeClassGrades(AcademicClass $class, int $userId): array
    {
        $this->validationService->validateClassStatus($class);

        return DB::transaction(function () use ($class, $userId) {
            // 1. Lock assessment scheme row
            $scheme = AssessmentScheme::where('academic_class_id', $class->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$scheme) {
                throw new UnprocessableEntityHttpException(
                    "Kelas {$class->code} tidak memiliki skema penilaian aktif untuk difinalisasi."
                );
            }

            // 2. Validate scheme total weight
            if (abs((float) $scheme->total_weight - 100.00) > 0.01) {
                throw new UnprocessableEntityHttpException(
                    "Skema penilaian belum mencapai 100% (saat ini: {$scheme->total_weight}%)."
                );
            }

            // 3. Validate completeness
            $this->validationService->validateRequiredComponentsForFinalization($class, $scheme);

            // 4. Lock relevant student grades
            $grades = StudentGrade::where('academic_class_id', $class->id)
                ->lockForUpdate()
                ->get();

            if ($grades->isEmpty()) {
                throw new UnprocessableEntityHttpException(
                    "Belum ada nilai yang diinputkan untuk kelas {$class->code}."
                );
            }

            // 5. Update grades status to FINAL
            StudentGrade::where('academic_class_id', $class->id)
                ->update([
                    'status' => GradeStatus::FINAL,
                ]);

            // 6. Calculate finalized class recap
            $recap = $this->calculationService->calculateClassGradeRecap($class);

            // 7. Audit log creation
            AuditService::log(
                action: 'grades.finalize',
                module: 'assessment',
                description: "Finalisasi nilai kelas {$class->code}",
                entity: $class,
                oldValues: ['status' => 'submitted'],
                newValues: [
                    'status' => 'final',
                    'finalized_by' => $userId,
                    'total_students' => $recap['summary']['total_students'],
                    'average_score' => $recap['summary']['average_score'],
                ],
                user: \Modules\Identity\Models\User::find($userId)
            );

            return $recap;
        });
    }

    /**
     * Finalize grades for a specific individual student in an academic class.
     */
    public function finalizeStudentGrades(AcademicClass $class, Student $student, int $userId): array
    {
        $this->validationService->validateClassStatus($class);
        $this->validationService->validateStudentEnrollment($student, $class);

        return DB::transaction(function () use ($class, $student, $userId) {
            $scheme = AssessmentScheme::where('academic_class_id', $class->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$scheme) {
                throw new UnprocessableEntityHttpException("Kelas {$class->code} tidak memiliki skema penilaian aktif.");
            }

            // Lock student's grades
            StudentGrade::where('academic_class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->update([
                    'status' => GradeStatus::FINAL,
                ]);

            $calc = $this->calculationService->calculateStudentFinalScore($student, $class, $scheme);

            AuditService::log(
                action: 'student_grades.finalize',
                module: 'assessment',
                description: "Finalisasi nilai mahasiswa {$student->student_number} kelas {$class->code}",
                entity: $student,
                oldValues: ['status' => 'submitted'],
                newValues: [
                    'status' => 'final',
                    'academic_class_id' => $class->id,
                    'final_score' => $calc['final_score'],
                    'letter_grade' => $calc['letter_grade'],
                    'grade_point' => $calc['grade_point'],
                    'finalized_by' => $userId,
                ],
                user: \Modules\Identity\Models\User::find($userId)
            );

            return $calc;
        });
    }
}
