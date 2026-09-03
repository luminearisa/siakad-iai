<?php

namespace Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assessment\Models\StudentGrade;
use Modules\Assessment\Requests\BatchGradeRequest;
use Modules\Assessment\Requests\GradeRevisionRequest;
use Modules\Assessment\Requests\StudentGradeRequest;
use Modules\Assessment\Resources\GradeRevisionResource;
use Modules\Assessment\Resources\StudentGradeResource;
use Modules\Assessment\Services\GradeCalculationService;
use Modules\Assessment\Services\GradeFinalizationService;
use Modules\Assessment\Services\GradeWorkflowService;
use Modules\Class\Models\AcademicClass;
use Modules\Student\Models\Student;

class StudentGradeController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected GradeWorkflowService $workflowService,
        protected GradeCalculationService $calculationService,
        protected GradeFinalizationService $finalizationService
    ) {}

    /**
     * Get all grades and calculated recap for an academic class.
     */
    public function classGrades(AcademicClass $class): JsonResponse
    {
        $recap = $this->calculationService->calculateClassGradeRecap($class);

        return $this->successResponse(
            data: $recap,
            message: 'Class grades retrieved successfully.'
        );
    }

    /**
     * Get grades and final score calculation for a specific student in a class.
     */
    public function studentClassGrades(AcademicClass $class, Student $student): JsonResponse
    {
        $calc = $this->calculationService->calculateStudentFinalScore($student, $class);

        return $this->successResponse(
            data: [
                'class' => [
                    'id' => $class->id,
                    'code' => $class->code,
                    'name' => $class->name,
                    'section' => $class->section,
                ],
                'student' => [
                    'id' => $student->id,
                    'student_number' => $student->student_number,
                    'full_name' => $student->full_name,
                ],
                'assessment' => $calc,
            ],
            message: 'Student class grades retrieved successfully.'
        );
    }

    /**
     * Input or update grades in batch for a class.
     */
    public function storeBatch(BatchGradeRequest $request, AcademicClass $class): JsonResponse
    {
        $userId = $request->user()?->id;
        $count = $this->workflowService->recordBatchGrades($class, $request->input('grades'), $userId);

        return $this->successResponse(
            data: ['count' => $count],
            message: "{$count} student grades recorded successfully."
        );
    }

    /**
     * Update a single student grade.
     */
    public function updateSingle(StudentGradeRequest $request, StudentGrade $grade): JsonResponse
    {
        $class = $grade->academicClass;
        $student = Student::findOrFail($request->input('student_id'));
        $component = $grade->component;
        $userId = $request->user()?->id;

        $updated = $this->workflowService->recordSingleGrade(
            class: $class,
            student: $student,
            component: $component,
            score: (float) $request->input('score'),
            userId: $userId,
            notes: $request->input('notes')
        );

        return $this->successResponse(
            data: new StudentGradeResource($updated->load(['student', 'component'])),
            message: 'Student grade updated successfully.'
        );
    }

    /**
     * Submit all draft grades for class review.
     */
    public function submitClassGrades(Request $request, AcademicClass $class): JsonResponse
    {
        $userId = $request->user()?->id;
        $affected = $this->workflowService->submitGrades($class, $userId);

        return $this->successResponse(
            data: ['submitted_count' => $affected],
            message: "Class grades submitted successfully ({$affected} items)."
        );
    }

    /**
     * Finalize class grades (Admin / Reviewer).
     */
    public function finalizeClassGrades(Request $request, AcademicClass $class): JsonResponse
    {
        $userId = $request->user()?->id;
        $recap = $this->finalizationService->finalizeClassGrades($class, (int) $userId);

        return $this->successResponse(
            data: $recap,
            message: 'Class grades have been finalized and locked successfully.'
        );
    }

    /**
     * Request revision on a submitted grade item.
     */
    public function requestRevision(Request $request, StudentGrade $grade): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $userId = $request->user()?->id;
        $updated = $this->workflowService->requestRevision($grade, $validated['reason'], $userId);

        return $this->successResponse(
            data: new StudentGradeResource($updated),
            message: 'Grade revision requested successfully.'
        );
    }

    /**
     * Formally revise a final grade item with reason and audit history.
     */
    public function reviseFinalGrade(GradeRevisionRequest $request, StudentGrade $grade): JsonResponse
    {
        $userId = (int) $request->user()?->id;
        $updated = $this->workflowService->reviseFinalGrade(
            grade: $grade,
            newScore: (float) $request->input('new_score'),
            reason: (string) $request->input('reason'),
            userId: $userId
        );

        return $this->successResponse(
            data: new StudentGradeResource($updated),
            message: 'Final grade revised successfully with audit log created.'
        );
    }

    /**
     * Get revision history for a student grade item.
     */
    public function revisionsHistory(StudentGrade $grade): JsonResponse
    {
        $revisions = $grade->revisions()->with('modifier')->get();

        return $this->successResponse(
            data: GradeRevisionResource::collection($revisions),
            message: 'Grade revision history retrieved successfully.'
        );
    }
}
