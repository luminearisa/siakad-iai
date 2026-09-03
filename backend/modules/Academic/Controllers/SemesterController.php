<?php

namespace Modules\Academic\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\Semester;
use Modules\Academic\Requests\SemesterRequest;
use Modules\Academic\Resources\SemesterResource;

class SemesterController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Semester::with('academicYear');

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name'],
            filterableColumns: ['status', 'type', 'academic_year_id'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Semesters retrieved successfully.',
            resourceClass: SemesterResource::class
        );
    }

    public function store(SemesterRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (($data['status'] ?? '') === 'active') {
            Semester::query()->update(['status' => 'inactive']);
            // Also activate the parent academic year
            if (!empty($data['academic_year_id'])) {
                \Modules\Academic\Models\AcademicYear::where('id', '!=', $data['academic_year_id'])->update(['status' => 'inactive']);
                \Modules\Academic\Models\AcademicYear::where('id', $data['academic_year_id'])->update(['status' => 'active']);
            }
        }

        $semester = Semester::create($data);

        return $this->successResponse(
            data: new SemesterResource($semester->load('academicYear')),
            message: 'Semester created successfully.',
            code: 201
        );
    }

    public function show(Semester $semester): JsonResponse
    {
        return $this->successResponse(
            data: new SemesterResource($semester->load('academicYear')),
            message: 'Semester retrieved successfully.'
        );
    }

    public function update(SemesterRequest $request, Semester $semester): JsonResponse
    {
        $data = $request->validated();
        if (($data['status'] ?? '') === 'active') {
            Semester::where('id', '!=', $semester->id)->update(['status' => 'inactive']);
            $academicYearId = $data['academic_year_id'] ?? $semester->academic_year_id;
            if ($academicYearId) {
                \Modules\Academic\Models\AcademicYear::where('id', '!=', $academicYearId)->update(['status' => 'inactive']);
                \Modules\Academic\Models\AcademicYear::where('id', $academicYearId)->update(['status' => 'active']);
            }
        }

        $semester->update($data);

        return $this->successResponse(
            data: new SemesterResource($semester->load('academicYear')),
            message: 'Semester updated successfully.'
        );
    }

    public function setActive(Semester $semester): JsonResponse
    {
        Semester::where('id', '!=', $semester->id)->update(['status' => 'inactive']);
        $semester->update(['status' => 'active']);

        if ($semester->academic_year_id) {
            \Modules\Academic\Models\AcademicYear::where('id', '!=', $semester->academic_year_id)->update(['status' => 'inactive']);
            \Modules\Academic\Models\AcademicYear::where('id', $semester->academic_year_id)->update(['status' => 'active']);
        }

        return $this->successResponse(
            data: new SemesterResource($semester->load('academicYear')),
            message: "Semester {$semester->name} berhasil diaktifkan."
        );
    }

    public function destroy(Semester $semester): JsonResponse
    {
        $semester->delete();

        return $this->successResponse(
            data: null,
            message: 'Semester deleted successfully.'
        );
    }
}
