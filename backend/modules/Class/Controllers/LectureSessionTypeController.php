<?php

namespace Modules\Class\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Class\Models\LectureSessionType;

class LectureSessionTypeController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $types = LectureSessionType::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $types,
            message: 'Lecture session types retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:50'],
            'credit_type' => ['required', 'string', 'max:50'],
            'counts_attendance' => ['required', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $type = LectureSessionType::create($validated);

        return $this->successResponse(
            data: $type,
            message: 'Lecture session type created successfully.',
            code: 201
        );
    }

    public function show(LectureSessionType $sessionType): JsonResponse
    {
        return $this->successResponse(
            data: $sessionType,
            message: 'Lecture session type retrieved successfully.'
        );
    }

    public function update(Request $request, LectureSessionType $sessionType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:50'],
            'credit_type' => ['required', 'string', 'max:50'],
            'counts_attendance' => ['required', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $sessionType->update($validated);

        return $this->successResponse(
            data: $sessionType,
            message: 'Lecture session type updated successfully.'
        );
    }

    public function destroy(LectureSessionType $sessionType): JsonResponse
    {
        $sessionType->delete();

        return $this->successResponse(
            data: null,
            message: 'Lecture session type deleted successfully.'
        );
    }
}
