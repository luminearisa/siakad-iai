<?php

namespace Modules\Assessment\Services;

use Illuminate\Support\Facades\DB;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\GradeRevision;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Models\AcademicClass;
use Modules\Student\Models\Student;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GradeWorkflowService
{
    public function __construct(
        protected GradeValidationService $validationService
    ) {}

    /**
     * Record or update a student's grade for a specific component.
     */
    public function recordSingleGrade(
        AcademicClass $class,
        Student $student,
        AssessmentComponent $component,
        float $score,
        ?int $userId = null,
        ?string $notes = null
    ): StudentGrade {
        $this->validationService->validateClassStatus($class);
        $this->validationService->validateStudentEnrollment($student, $class);
        $this->validationService->validateComponentBelongsToClass($component, $class);
        $this->validationService->validateScoreBounds($score, $component);

        $grade = StudentGrade::where('student_id', $student->id)
            ->where('academic_class_id', $class->id)
            ->where('assessment_component_id', $component->id)
            ->first();

        $this->validationService->validateNoDirectFinalModification($grade);

        return StudentGrade::updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_class_id' => $class->id,
                'assessment_component_id' => $component->id,
            ],
            [
                'score' => $score,
                'graded_by' => $userId,
                'graded_at' => now(),
                'status' => GradeStatus::DRAFT,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Record grades in batch for an academic class.
     *
     * @param AcademicClass $class
     * @param array<array{student_id: int, assessment_component_id: int, score: float, notes?: string}> $grades
     * @param int|null $userId
     * @return int Count of recorded grades
     */
    public function recordBatchGrades(AcademicClass $class, array $grades, ?int $userId = null): int
    {
        $this->validationService->validateClassStatus($class);

        return DB::transaction(function () use ($class, $grades, $userId) {
            $count = 0;
            foreach ($grades as $item) {
                $student = Student::findOrFail($item['student_id']);
                $component = AssessmentComponent::findOrFail($item['assessment_component_id']);

                $this->recordSingleGrade(
                    class: $class,
                    student: $student,
                    component: $component,
                    score: (float) $item['score'],
                    userId: $userId,
                    notes: $item['notes'] ?? null
                );
                $count++;
            }

            return $count;
        });
    }

    /**
     * Submit all draft or revision-required grades of a class for administrative review.
     */
    public function submitGrades(AcademicClass $class, ?int $userId = null): int
    {
        $this->validationService->validateClassStatus($class);
        $this->validationService->validateAssessmentSchemeActive($class);

        $affected = StudentGrade::where('academic_class_id', $class->id)
            ->whereIn('status', [GradeStatus::DRAFT, GradeStatus::REVISION_REQUIRED])
            ->update([
                'status' => GradeStatus::SUBMITTED,
                'graded_by' => $userId ?: DB::raw('graded_by'),
            ]);

        return $affected;
    }

    /**
     * Request revision for a submitted grade item (e.g. by reviewer/admin).
     */
    public function requestRevision(StudentGrade $grade, string $reason, ?int $userId = null): StudentGrade
    {
        if ($grade->status === GradeStatus::FINAL) {
            throw new UnprocessableEntityHttpException(
                'Nilai final tidak dapat diminta revisi draft. Gunakan fitur revisi nilai resmi (Revise Grade).'
            );
        }

        $grade->update([
            'status' => GradeStatus::REVISION_REQUIRED,
            'notes' => $reason ? "Revisi Diminta: {$reason}" : $grade->notes,
        ]);

        return $grade->fresh(['student', 'component']);
    }

    /**
     * Formally revise a final grade with immutable revision audit history.
     */
    public function reviseFinalGrade(
        StudentGrade $grade,
        float $newScore,
        string $reason,
        int $userId
    ): StudentGrade {
        if (trim($reason) === '') {
            throw new UnprocessableEntityHttpException('Alasan perubahan nilai (reason) wajib diisi untuk revisi nilai final.');
        }

        $component = $grade->component;
        if ($component) {
            $this->validationService->validateScoreBounds($newScore, $component);
        }

        return DB::transaction(function () use ($grade, $newScore, $reason, $userId) {
            $oldScore = (float) $grade->score;

            // 1. Create immutable revision record
            GradeRevision::create([
                'student_grade_id' => $grade->id,
                'old_score' => $oldScore,
                'new_score' => $newScore,
                'reason' => $reason,
                'changed_by' => $userId,
                'changed_at' => now(),
            ]);

            // 2. Update the student grade
            $grade->update([
                'score' => $newScore,
                'graded_by' => $userId,
                'graded_at' => now(),
                'notes' => "Direvisi pada " . now()->format('d/m/Y H:i') . ": {$reason}",
            ]);

            return $grade->fresh(['student', 'component', 'revisions.modifier']);
        });
    }
}
