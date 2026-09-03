<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\SubCourseLearningOutcome;

class SubCpmkController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $subCpmks = SubCourseLearningOutcome::with('courseOutcome')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->course_learning_outcome_id, function ($q, $cpmkId) {
                $q->where('course_learning_outcome_id', $cpmkId);
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $subCpmks,
            message: 'Sub-CPMKs retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_learning_outcome_id' => ['nullable', 'integer', 'exists:course_learning_outcomes,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $sub = SubCourseLearningOutcome::create($validated);

        return $this->successResponse(
            data: $sub->load('courseOutcome'),
            message: 'Sub-CPMK created successfully.',
            code: 201
        );
    }

    public function show(SubCourseLearningOutcome $subCpmk): JsonResponse
    {
        return $this->successResponse(
            data: $subCpmk->load('courseOutcome'),
            message: 'Sub-CPMK retrieved successfully.'
        );
    }

    public function update(Request $request, SubCourseLearningOutcome $subCpmk): JsonResponse
    {
        $validated = $request->validate([
            'course_learning_outcome_id' => ['nullable', 'integer', 'exists:course_learning_outcomes,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $subCpmk->update($validated);

        return $this->successResponse(
            data: $subCpmk->load('courseOutcome'),
            message: 'Sub-CPMK updated successfully.'
        );
    }

    public function destroy(SubCourseLearningOutcome $subCpmk): JsonResponse
    {
        $subCpmk->delete();

        return $this->successResponse(
            data: null,
            message: 'Sub-CPMK deleted successfully.'
        );
    }
}
