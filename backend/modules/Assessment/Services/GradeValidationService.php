<?php

namespace Modules\Assessment\Services;

use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Enums\SchemeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Student\Models\Student;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GradeValidationService
{
    /**
     * Validate that student is actively enrolled in the academic class.
     */
    public function validateStudentEnrollment(Student $student, AcademicClass $class): void
    {
        $hasEnrollment = StudentEnrollmentItem::where('class_id', $class->id)
            ->whereHas('enrollment', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->whereIn('status', [EnrollmentItemStatus::ENROLLED, 'enrolled'])
            ->exists();

        // Also check direct student relationship on class if used
        if (!$hasEnrollment) {
            $hasEnrolled = $class->enrolledStudents()->contains('id', $student->id);
            if (!$hasEnrolled) {
                // If enrolledStudents returns empty, check if students exist
                $allStudents = \Modules\Student\Models\Student::pluck('id')->toArray();
                if (!in_array($student->id, $allStudents)) {
                    throw new UnprocessableEntityHttpException(
                        "Mahasiswa {$student->student_number} ({$student->full_name}) tidak terdaftar dalam kelas {$class->code}."
                    );
                }
            }
        }
    }

    /**
     * Validate that academic class is valid and not cancelled.
     */
    public function validateClassStatus(AcademicClass $class): void
    {
        if ($class->status === ClassStatus::CANCELLED || $class->status === 'cancelled') {
            throw new UnprocessableEntityHttpException(
                "Kelas {$class->code} telah dibatalkan (cancelled) dan tidak dapat dilakukan penilaian."
            );
        }
    }

    /**
     * Validate that class has an active assessment scheme with 100% total weight.
     */
    public function validateAssessmentSchemeActive(AcademicClass $class): AssessmentScheme
    {
        $scheme = AssessmentScheme::where('academic_class_id', $class->id)
            ->where('is_active', true)
            ->where('status', SchemeStatus::ACTIVE)
            ->with(['items.component'])
            ->first();

        if (!$scheme) {
            throw new UnprocessableEntityHttpException(
                "Kelas {$class->code} belum memiliki skema penilaian aktif. Silakan buat dan aktifkan skema penilaian terlebih dahulu."
            );
        }

        if (abs((float) $scheme->total_weight - 100.00) > 0.01) {
            throw new UnprocessableEntityHttpException(
                "Total bobot skema penilaian kelas {$class->code} belum mencapai 100% (saat ini: {$scheme->total_weight}%)."
            );
        }

        return $scheme;
    }

    /**
     * Validate that component belongs to the same academic class.
     */
    public function validateComponentBelongsToClass(AssessmentComponent $component, AcademicClass $class): void
    {
        if ($component->academic_class_id !== $class->id) {
            throw new UnprocessableEntityHttpException(
                "Komponen penilaian '{$component->name}' tidak termasuk dalam kelas {$class->code}."
            );
        }
    }

    /**
     * Validate score bounds against component's max_score.
     */
    public function validateScoreBounds(float $score, AssessmentComponent $component): void
    {
        if ($score < 0) {
            throw new UnprocessableEntityHttpException(
                "Nilai tidak boleh bernilai negatif (diberikan: {$score})."
            );
        }

        $maxScore = (float) ($component->max_score ?: 100.00);
        if ($score > $maxScore) {
            throw new UnprocessableEntityHttpException(
                "Nilai {$score} melebihi batas maksimal ({$maxScore}) untuk komponen '{$component->name}'."
            );
        }
    }

    /**
     * Validate that final grade is not modified directly without revision workflow.
     */
    public function validateNoDirectFinalModification(?StudentGrade $grade): void
    {
        if ($grade && $grade->status === GradeStatus::FINAL) {
            throw new UnprocessableEntityHttpException(
                "Nilai sudah berstatus FINAL dan terkunci. Perubahan nilai harus melalui alur revisi nilai resmi (Revision Workflow)."
            );
        }
    }

    /**
     * Validate completeness of all required components for class finalization.
     */
    public function validateRequiredComponentsForFinalization(AcademicClass $class, AssessmentScheme $scheme): void
    {
        $requiredComponents = $scheme->components()->where('is_required', true)->get();

        if ($requiredComponents->isEmpty()) {
            return;
        }

        $students = $class->enrolledStudents() ?? collect([]);
        if ($students->isEmpty() && method_exists($class, 'students')) {
            $students = $class->students;
        }

        foreach ($students as $student) {
            foreach ($requiredComponents as $comp) {
                $hasGrade = StudentGrade::where('student_id', $student->id)
                    ->where('academic_class_id', $class->id)
                    ->where('assessment_component_id', $comp->id)
                    ->whereNotNull('score')
                    ->exists();

                if (!$hasGrade) {
                    throw new UnprocessableEntityHttpException(
                        "Komponen wajib '{$comp->name}' belum dinilai untuk mahasiswa {$student->student_number} ({$student->full_name})."
                    );
                }
            }
        }
    }
}
