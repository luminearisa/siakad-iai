<?php

namespace Modules\Course\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Course\Models\CourseGroup;

class CourseGroupController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $groups = CourseGroup::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $groups,
            message: 'Course groups retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:course_groups,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $group = CourseGroup::create($validated);

        return $this->successResponse(
            data: $group,
            message: 'Course group created successfully.',
            code: 201
        );
    }

    public function show(CourseGroup $group): JsonResponse
    {
        return $this->successResponse(
            data: $group,
            message: 'Course group retrieved successfully.'
        );
    }

    public function update(Request $request, CourseGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:course_groups,code,' . $group->id],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $group->update($validated);

        return $this->successResponse(
            data: $group,
            message: 'Course group updated successfully.'
        );
    }

    public function destroy(CourseGroup $group): JsonResponse
    {
        $group->delete();

        return $this->successResponse(
            data: null,
            message: 'Course group deleted successfully.'
        );
    }
}
