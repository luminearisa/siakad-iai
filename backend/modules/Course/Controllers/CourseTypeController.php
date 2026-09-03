<?php

namespace Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\CourseType;

class CourseTypeController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $types = CourseType::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $types,
            message: 'Course types retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:course_types,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $type = CourseType::create($validated);

        return $this->successResponse(
            data: $type,
            message: 'Course type created successfully.',
            code: 201
        );
    }

    public function show(CourseType $type): JsonResponse
    {
        return $this->successResponse(
            data: $type,
            message: 'Course type retrieved successfully.'
        );
    }

    public function update(Request $request, CourseType $type): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:course_types,code,' . $type->id],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $type->update($validated);

        return $this->successResponse(
            data: $type,
            message: 'Course type updated successfully.'
        );
    }

    public function destroy(CourseType $type): JsonResponse
    {
        $type->delete();

        return $this->successResponse(
            data: null,
            message: 'Course type deleted successfully.'
        );
    }
}
