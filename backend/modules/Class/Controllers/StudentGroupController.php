<?php

namespace Modules\Class\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Class\Models\StudentGroup;

class StudentGroupController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $groups = StudentGroup::query()
            ->with(['studyProgram.faculty'])
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $groups,
            message: 'Student groups retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'student_count' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $group = StudentGroup::create($validated);

        return $this->successResponse(
            data: $group->load('studyProgram'),
            message: 'Student group created successfully.',
            code: 201
        );
    }

    public function show(StudentGroup $studentGroup): JsonResponse
    {
        return $this->successResponse(
            data: $studentGroup->load('studyProgram'),
            message: 'Student group retrieved successfully.'
        );
    }

    public function update(Request $request, StudentGroup $studentGroup): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'student_count' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $studentGroup->update($validated);

        return $this->successResponse(
            data: $studentGroup->load('studyProgram'),
            message: 'Student group updated successfully.'
        );
    }

    public function destroy(StudentGroup $studentGroup): JsonResponse
    {
        $studentGroup->delete();

        return $this->successResponse(
            data: null,
            message: 'Student group deleted successfully.'
        );
    }
}
