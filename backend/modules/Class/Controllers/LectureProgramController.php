<?php

namespace Modules\Class\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Class\Models\LectureProgram;

class LectureProgramController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $programs = LectureProgram::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $programs,
            message: 'Lecture programs retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $program = LectureProgram::create($validated);

        return $this->successResponse(
            data: $program,
            message: 'Lecture program created successfully.',
            code: 201
        );
    }

    public function show(LectureProgram $lectureProgram): JsonResponse
    {
        return $this->successResponse(
            data: $lectureProgram,
            message: 'Lecture program retrieved successfully.'
        );
    }

    public function update(Request $request, LectureProgram $lectureProgram): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $lectureProgram->update($validated);

        return $this->successResponse(
            data: $lectureProgram,
            message: 'Lecture program updated successfully.'
        );
    }

    public function destroy(LectureProgram $lectureProgram): JsonResponse
    {
        $lectureProgram->delete();

        return $this->successResponse(
            data: null,
            message: 'Lecture program deleted successfully.'
        );
    }
}
