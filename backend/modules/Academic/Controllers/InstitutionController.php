<?php

namespace Modules\Academic\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\Institution;
use Modules\Academic\Requests\InstitutionRequest;
use Modules\Academic\Resources\InstitutionResource;

class InstitutionController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Institution::with('faculties');

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code', 'short_name'],
            filterableColumns: ['status'],
            defaultSort: 'id',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Institutions retrieved successfully.',
            resourceClass: InstitutionResource::class
        );
    }

    public function store(InstitutionRequest $request): JsonResponse
    {
        $institution = Institution::create($request->validated());

        return $this->successResponse(
            data: new InstitutionResource($institution),
            message: 'Institution created successfully.',
            code: 201
        );
    }

    public function show(Institution $institution): JsonResponse
    {
        return $this->successResponse(
            data: new InstitutionResource($institution->load('faculties.studyPrograms')),
            message: 'Institution retrieved successfully.'
        );
    }

    public function update(InstitutionRequest $request, Institution $institution): JsonResponse
    {
        $institution->update($request->validated());

        return $this->successResponse(
            data: new InstitutionResource($institution),
            message: 'Institution updated successfully.'
        );
    }

    public function destroy(Institution $institution): JsonResponse
    {
        $institution->delete();

        return $this->successResponse(
            data: null,
            message: 'Institution deleted successfully.'
        );
    }
}
