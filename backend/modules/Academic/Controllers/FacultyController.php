<?php

namespace Modules\Academic\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Requests\FacultyRequest;
use Modules\Academic\Resources\FacultyResource;

class FacultyController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Faculty::with(['institution', 'studyPrograms']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code'],
            filterableColumns: ['status', 'institution_id'],
            defaultSort: 'id',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Faculties retrieved successfully.',
            resourceClass: FacultyResource::class
        );
    }

    public function store(FacultyRequest $request): JsonResponse
    {
        $faculty = Faculty::create($request->validated());

        return $this->successResponse(
            data: new FacultyResource($faculty->load('institution')),
            message: 'Faculty created successfully.',
            code: 201
        );
    }

    public function show(Faculty $faculty): JsonResponse
    {
        return $this->successResponse(
            data: new FacultyResource($faculty->load(['institution', 'studyPrograms'])),
            message: 'Faculty retrieved successfully.'
        );
    }

    public function update(FacultyRequest $request, Faculty $faculty): JsonResponse
    {
        $faculty->update($request->validated());

        return $this->successResponse(
            data: new FacultyResource($faculty->load('institution')),
            message: 'Faculty updated successfully.'
        );
    }

    public function destroy(Faculty $faculty): JsonResponse
    {
        $faculty->delete();

        return $this->successResponse(
            data: null,
            message: 'Faculty deleted successfully.'
        );
    }
}
