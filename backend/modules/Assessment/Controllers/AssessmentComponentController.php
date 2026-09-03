<?php

namespace Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Requests\AssessmentComponentRequest;
use Modules\Assessment\Resources\AssessmentComponentResource;
use Modules\Class\Models\AcademicClass;

class AssessmentComponentController extends Controller
{
    use HasApiResponse;

    /**
     * List components for a class.
     */
    public function classComponents(AcademicClass $class): JsonResponse
    {
        $components = AssessmentComponent::where('academic_class_id', $class->id)
            ->orderBy('sequence')
            ->get();

        return $this->successResponse(
            data: AssessmentComponentResource::collection($components),
            message: 'Class assessment components retrieved successfully.'
        );
    }

    /**
     * Create a new assessment component for a class.
     */
    public function store(AssessmentComponentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $classId = $data['academic_class_id'] ?? $this->route('class')?->id;

        $sequence = $data['sequence'] ?? (AssessmentComponent::where('academic_class_id', $classId)->max('sequence') + 1);

        $component = AssessmentComponent::create([
            'academic_class_id' => $classId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'type' => $data['type'],
            'max_score' => $data['max_score'] ?? 100.00,
            'is_required' => $data['is_required'] ?? false,
            'sequence' => $sequence,
        ]);

        return $this->successResponse(
            data: new AssessmentComponentResource($component),
            message: 'Assessment component created successfully.',
            code: 201
        );
    }

    /**
     * Show assessment component detail.
     */
    public function show(AssessmentComponent $component): JsonResponse
    {
        return $this->successResponse(
            data: new AssessmentComponentResource($component),
            message: 'Assessment component details retrieved successfully.'
        );
    }

    /**
     * Update an assessment component.
     */
    public function update(AssessmentComponentRequest $request, AssessmentComponent $component): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $component->update($data);

        return $this->successResponse(
            data: new AssessmentComponentResource($component),
            message: 'Assessment component updated successfully.'
        );
    }

    /**
     * Delete an assessment component.
     */
    public function destroy(AssessmentComponent $component): JsonResponse
    {
        $component->delete();

        return $this->successResponse(
            data: null,
            message: 'Assessment component deleted successfully.'
        );
    }
}
