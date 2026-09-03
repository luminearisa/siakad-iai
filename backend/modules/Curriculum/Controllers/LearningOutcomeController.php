<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\LearningOutcome;

class LearningOutcomeController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $outcomes = LearningOutcome::with('studyProgram')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->category, function ($q, $category) {
                $q->where('category', $category);
            })
            ->when($request->study_program_id, function ($q, $prodiId) {
                $q->where('study_program_id', $prodiId);
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $outcomes,
            message: 'Learning outcomes (CPL) retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string'],
            'category' => ['required', 'string', 'in:sikap,pengetahuan,keterampilan_umum,keterampilan_khusus'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $cpl = LearningOutcome::create($validated);

        return $this->successResponse(
            data: $cpl->load('studyProgram'),
            message: 'Learning outcome (CPL) created successfully.',
            code: 201
        );
    }

    public function show(LearningOutcome $learningOutcome): JsonResponse
    {
        return $this->successResponse(
            data: $learningOutcome->load('studyProgram'),
            message: 'Learning outcome (CPL) retrieved successfully.'
        );
    }

    public function update(Request $request, LearningOutcome $learningOutcome): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string'],
            'category' => ['required', 'string', 'in:sikap,pengetahuan,keterampilan_umum,keterampilan_khusus'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $learningOutcome->update($validated);

        return $this->successResponse(
            data: $learningOutcome->load('studyProgram'),
            message: 'Learning outcome (CPL) updated successfully.'
        );
    }

    public function destroy(LearningOutcome $learningOutcome): JsonResponse
    {
        $learningOutcome->delete();

        return $this->successResponse(
            data: null,
            message: 'Learning outcome (CPL) deleted successfully.'
        );
    }
}
