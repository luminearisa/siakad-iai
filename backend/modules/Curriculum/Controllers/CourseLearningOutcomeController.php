<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\CourseLearningOutcome;

class CourseLearningOutcomeController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $cpmks = CourseLearningOutcome::with(['studyProgram', 'course', 'learningOutcome', 'subOutcomes'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->study_program_id, function ($q, $prodiId) {
                $q->where('study_program_id', $prodiId);
            })
            ->when($request->course_id, function ($q, $courseId) {
                $q->where('course_id', $courseId);
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $cpmks,
            message: 'Course learning outcomes (CPMK) retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'learning_outcome_id' => ['nullable', 'integer', 'exists:learning_outcomes,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $cpmk = CourseLearningOutcome::create($validated);

        return $this->successResponse(
            data: $cpmk->load(['studyProgram', 'course', 'learningOutcome']),
            message: 'CPMK created successfully.',
            code: 201
        );
    }

    public function show(CourseLearningOutcome $courseLearningOutcome): JsonResponse
    {
        return $this->successResponse(
            data: $courseLearningOutcome->load(['studyProgram', 'course', 'learningOutcome', 'subOutcomes']),
            message: 'CPMK retrieved successfully.'
        );
    }

    public function update(Request $request, CourseLearningOutcome $courseLearningOutcome): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'learning_outcome_id' => ['nullable', 'integer', 'exists:learning_outcomes,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $courseLearningOutcome->update($validated);

        return $this->successResponse(
            data: $courseLearningOutcome->load(['studyProgram', 'course', 'learningOutcome']),
            message: 'CPMK updated successfully.'
        );
    }

    public function destroy(CourseLearningOutcome $courseLearningOutcome): JsonResponse
    {
        $courseLearningOutcome->delete();

        return $this->successResponse(
            data: null,
            message: 'CPMK deleted successfully.'
        );
    }
}
