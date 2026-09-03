<?php

namespace Modules\Academic\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Requests\AcademicYearRequest;
use Modules\Academic\Resources\AcademicYearResource;

class AcademicYearController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = AcademicYear::with('semesters');

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name'],
            filterableColumns: ['status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Academic years retrieved successfully.',
            resourceClass: AcademicYearResource::class
        );
    }

    public function store(AcademicYearRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (($data['status'] ?? '') === 'active') {
            AcademicYear::query()->update(['status' => 'inactive']);
        }

        $academicYear = AcademicYear::create($data);

        return $this->successResponse(
            data: new AcademicYearResource($academicYear),
            message: 'Academic year created successfully.',
            code: 201
        );
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return $this->successResponse(
            data: new AcademicYearResource($academicYear->load('semesters')),
            message: 'Academic year retrieved successfully.'
        );
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $request->validated();
        if (($data['status'] ?? '') === 'active') {
            AcademicYear::where('id', '!=', $academicYear->id)->update(['status' => 'inactive']);
            // Also ensure active semester matches this academic year if it has semesters
            $firstSemester = $academicYear->semesters()->first();
            if ($firstSemester) {
                \Modules\Academic\Models\Semester::query()->update(['status' => 'inactive']);
                $firstSemester->update(['status' => 'active']);
            }
        }

        $academicYear->update($data);

        return $this->successResponse(
            data: new AcademicYearResource($academicYear),
            message: 'Academic year updated successfully.'
        );
    }

    public function setActive(AcademicYear $academicYear): JsonResponse
    {
        AcademicYear::where('id', '!=', $academicYear->id)->update(['status' => 'inactive']);
        $academicYear->update(['status' => 'active']);

        // Sync semester
        $semester = $academicYear->semesters()->first();
        if ($semester) {
            \Modules\Academic\Models\Semester::query()->update(['status' => 'inactive']);
            $semester->update(['status' => 'active']);
        }

        return $this->successResponse(
            data: new AcademicYearResource($academicYear->load('semesters')),
            message: "Tahun ajaran {$academicYear->name} berhasil diaktifkan."
        );
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        $academicYear->delete();

        return $this->successResponse(
            data: null,
            message: 'Academic year deleted successfully.'
        );
    }
}
