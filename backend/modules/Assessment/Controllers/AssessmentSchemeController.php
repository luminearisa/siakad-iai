<?php

namespace Modules\Assessment\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Requests\AssessmentSchemeItemRequest;
use Modules\Assessment\Requests\AssessmentSchemeRequest;
use Modules\Assessment\Resources\AssessmentComponentResource;
use Modules\Assessment\Resources\AssessmentSchemeItemResource;
use Modules\Assessment\Resources\AssessmentSchemeResource;
use Modules\Assessment\Services\AssessmentSchemeService;
use Modules\Class\Models\AcademicClass;

class AssessmentSchemeController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected AssessmentSchemeService $schemeService
    ) {}

    /**
     * Get the active or main assessment scheme for an academic class.
     */
    public function classScheme(AcademicClass $class): JsonResponse
    {
        $scheme = AssessmentScheme::where('academic_class_id', $class->id)
            ->where('is_active', true)
            ->with(['items.component'])
            ->first()
            ?? AssessmentScheme::where('academic_class_id', $class->id)
                ->latest()
                ->with(['items.component'])
                ->first();

        if (!$scheme) {
            return $this->successResponse(
                data: null,
                message: 'No assessment scheme found for this class.'
            );
        }

        return $this->successResponse(
            data: new AssessmentSchemeResource($scheme),
            message: 'Assessment scheme retrieved successfully.'
        );
    }

    /**
     * Create an assessment scheme for an academic class.
     */
    public function storeForClass(AssessmentSchemeRequest $request, AcademicClass $class): JsonResponse
    {
        $scheme = $this->schemeService->createScheme($class, $request->validated());

        return $this->successResponse(
            data: new AssessmentSchemeResource($scheme),
            message: 'Assessment scheme created successfully.',
            code: 201
        );
    }

    /**
     * Show assessment scheme detail.
     */
    public function show(AssessmentScheme $scheme): JsonResponse
    {
        $scheme->load(['items.component', 'academicClass']);

        return $this->successResponse(
            data: new AssessmentSchemeResource($scheme),
            message: 'Assessment scheme details retrieved successfully.'
        );
    }

    /**
     * Update an assessment scheme.
     */
    public function update(AssessmentSchemeRequest $request, AssessmentScheme $scheme): JsonResponse
    {
        $updated = $this->schemeService->updateScheme($scheme, $request->validated());

        return $this->successResponse(
            data: new AssessmentSchemeResource($updated),
            message: 'Assessment scheme updated successfully.'
        );
    }

    /**
     * Delete an assessment scheme.
     */
    public function destroy(AssessmentScheme $scheme): JsonResponse
    {
        $this->schemeService->deleteScheme($scheme);

        return $this->successResponse(
            data: null,
            message: 'Assessment scheme deleted successfully.'
        );
    }

    /**
     * Activate an assessment scheme.
     */
    public function activate(AssessmentScheme $scheme): JsonResponse
    {
        $activated = $this->schemeService->activateScheme($scheme);

        return $this->successResponse(
            data: new AssessmentSchemeResource($activated),
            message: 'Assessment scheme activated successfully.'
        );
    }

    /**
     * Archive an assessment scheme.
     */
    public function archive(AssessmentScheme $scheme): JsonResponse
    {
        $archived = $this->schemeService->archiveScheme($scheme);

        return $this->successResponse(
            data: new AssessmentSchemeResource($archived),
            message: 'Assessment scheme archived successfully.'
        );
    }

    /**
     * Get components and items in a scheme.
     */
    public function components(AssessmentScheme $scheme): JsonResponse
    {
        $scheme->load('items.component');

        return $this->successResponse(
            data: AssessmentSchemeItemResource::collection($scheme->items),
            message: 'Assessment scheme components retrieved successfully.'
        );
    }

    /**
     * Add or attach component to an assessment scheme with weight.
     */
    public function addComponent(AssessmentSchemeItemRequest $request, AssessmentScheme $scheme): JsonResponse
    {
        $item = $this->schemeService->addComponentToScheme(
            $scheme,
            (int) $request->input('assessment_component_id'),
            (float) $request->input('weight')
        );

        return $this->successResponse(
            data: new AssessmentSchemeItemResource($item->load('component')),
            message: 'Component attached to assessment scheme successfully.'
        );
    }
}
