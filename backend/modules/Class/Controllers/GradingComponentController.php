<?php

namespace Modules\Class\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Class\Models\GradingComponent;

class GradingComponentController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $components = GradingComponent::query()
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%")
                  ->orWhere('evaluation_method', 'like', "%{$search}%")
                  ->orWhere('component_group', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $components,
            message: 'Grading components retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:50'],
            'evaluation_method' => ['required', 'string', 'max:150'],
            'component_group' => ['nullable', 'string', 'max:100'],
            'default_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $component = GradingComponent::create($validated);

        return $this->successResponse(
            data: $component,
            message: 'Grading component created successfully.',
            code: 201
        );
    }

    public function show(GradingComponent $gradingComponent): JsonResponse
    {
        return $this->successResponse(
            data: $gradingComponent,
            message: 'Grading component retrieved successfully.'
        );
    }

    public function update(Request $request, GradingComponent $gradingComponent): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:50'],
            'evaluation_method' => ['required', 'string', 'max:150'],
            'component_group' => ['nullable', 'string', 'max:100'],
            'default_weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $gradingComponent->update($validated);

        return $this->successResponse(
            data: $gradingComponent,
            message: 'Grading component updated successfully.'
        );
    }

    public function destroy(GradingComponent $gradingComponent): JsonResponse
    {
        $gradingComponent->delete();

        return $this->successResponse(
            data: null,
            message: 'Grading component deleted successfully.'
        );
    }
}
