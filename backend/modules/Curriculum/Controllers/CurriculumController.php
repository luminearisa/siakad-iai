<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\Curriculum;
use Modules\Curriculum\Requests\CreateCurriculumRequest;
use Modules\Curriculum\Requests\CreateCurriculumSemesterRequest;
use Modules\Curriculum\Requests\UpdateCurriculumRequest;
use Modules\Curriculum\Resources\CurriculumResource;
use Modules\Curriculum\Resources\CurriculumSemesterResource;
use Modules\Curriculum\Services\CurriculumService;

class CurriculumController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected CurriculumService $curriculumService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Curriculum::with(['studyProgram.faculty']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code', 'version'],
            filterableColumns: ['status', 'study_program_id', 'start_year', 'end_year'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Curricula retrieved successfully.',
            resourceClass: CurriculumResource::class
        );
    }

    public function store(CreateCurriculumRequest $request): JsonResponse
    {
        $curriculum = $this->curriculumService->create($request->validated());

        return $this->successResponse(
            data: new CurriculumResource($curriculum),
            message: 'Curriculum created successfully.',
            code: 201
        );
    }

    public function show(Curriculum $curriculum): JsonResponse
    {
        return $this->successResponse(
            data: new CurriculumResource($curriculum->load([
                'studyProgram.faculty.institution',
                'semesters.subjects.course',
            ])),
            message: 'Curriculum retrieved successfully.'
        );
    }

    public function update(UpdateCurriculumRequest $request, Curriculum $curriculum): JsonResponse
    {
        $updated = $this->curriculumService->update($curriculum, $request->validated());

        return $this->successResponse(
            data: new CurriculumResource($updated),
            message: 'Curriculum updated successfully.'
        );
    }

    public function destroy(Request $request, Curriculum $curriculum): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('curricula.delete')) {
            return $this->errorResponse('Unauthorized to delete curriculum.', 403);
        }

        $curriculum->delete();

        return $this->successResponse(
            data: null,
            message: 'Curriculum deleted successfully.'
        );
    }

    public function activate(Request $request, Curriculum $curriculum): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('curricula.activate')) {
            return $this->errorResponse('Unauthorized to activate curriculum.', 403);
        }

        $activated = $this->curriculumService->activate($curriculum);

        return $this->successResponse(
            data: new CurriculumResource($activated->fresh(['studyProgram', 'semesters.subjects.course'])),
            message: 'Curriculum activated successfully.'
        );
    }

    public function archive(Request $request, Curriculum $curriculum): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('curricula.archive')) {
            return $this->errorResponse('Unauthorized to archive curriculum.', 403);
        }

        $archived = $this->curriculumService->archive($curriculum);

        return $this->successResponse(
            data: new CurriculumResource($archived),
            message: 'Curriculum archived successfully.'
        );
    }

    public function semesters(Curriculum $curriculum): JsonResponse
    {
        $semesters = $curriculum->semesters()->with('subjects.course')->get();

        return $this->successResponse(
            data: CurriculumSemesterResource::collection($semesters),
            message: 'Curriculum semesters retrieved successfully.'
        );
    }

    public function storeSemester(CreateCurriculumSemesterRequest $request, Curriculum $curriculum): JsonResponse
    {
        $semester = $curriculum->semesters()->create($request->validated());

        return $this->successResponse(
            data: new CurriculumSemesterResource($semester),
            message: 'Curriculum semester created successfully.',
            code: 201
        );
    }
}
