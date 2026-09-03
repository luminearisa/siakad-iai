<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\GradeScale;
use Modules\Curriculum\Models\GradeScaleItem;

class GradeScaleController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $scales = GradeScale::query()
            ->with(['items'])
            ->withCount('curricula')
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $scales,
            message: 'Grade scales retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.grade_letter' => ['required', 'string', 'max:10'],
            'items.*.grade_point' => ['required', 'numeric', 'min:0', 'max:4'],
            'items.*.min_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.max_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.is_whitewash' => ['nullable', 'boolean'],
        ]);

        $scale = GradeScale::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        foreach ($validated['items'] as $item) {
            $scale->items()->create([
                'grade_letter' => $item['grade_letter'],
                'grade_point' => $item['grade_point'],
                'min_score' => $item['min_score'],
                'max_score' => $item['max_score'],
                'is_whitewash' => $item['is_whitewash'] ?? false,
            ]);
        }

        return $this->successResponse(
            data: $scale->load('items'),
            message: 'Grade scale created successfully.',
            code: 201
        );
    }

    public function show(GradeScale $gradeScale): JsonResponse
    {
        return $this->successResponse(
            data: $gradeScale->load(['items', 'curricula']),
            message: 'Grade scale retrieved successfully.'
        );
    }

    public function update(Request $request, GradeScale $gradeScale): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'items' => ['nullable', 'array'],
            'items.*.grade_letter' => ['required_with:items', 'string', 'max:10'],
            'items.*.grade_point' => ['required_with:items', 'numeric', 'min:0', 'max:4'],
            'items.*.min_score' => ['required_with:items', 'numeric', 'min:0', 'max:100'],
            'items.*.max_score' => ['required_with:items', 'numeric', 'min:0', 'max:100'],
            'items.*.is_whitewash' => ['nullable', 'boolean'],
        ]);

        $gradeScale->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $gradeScale->description,
            'status' => $validated['status'] ?? $gradeScale->status,
        ]);

        if (isset($validated['items']) && is_array($validated['items'])) {
            $gradeScale->items()->delete();
            foreach ($validated['items'] as $item) {
                $gradeScale->items()->create([
                    'grade_letter' => $item['grade_letter'],
                    'grade_point' => $item['grade_point'],
                    'min_score' => $item['min_score'],
                    'max_score' => $item['max_score'],
                    'is_whitewash' => $item['is_whitewash'] ?? false,
                ]);
            }
        }

        return $this->successResponse(
            data: $gradeScale->load('items'),
            message: 'Grade scale updated successfully.'
        );
    }

    public function destroy(GradeScale $gradeScale): JsonResponse
    {
        $gradeScale->delete();

        return $this->successResponse(
            data: null,
            message: 'Grade scale deleted successfully.'
        );
    }
}
