<?php

namespace Modules\Academic\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\StudyProgram;
use Modules\Academic\Requests\StudyProgramRequest;
use Modules\Academic\Resources\StudyProgramResource;

class StudyProgramController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = StudyProgram::with(['faculty.institution', 'setting']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code'],
            filterableColumns: ['status', 'faculty_id', 'degree'],
            defaultSort: 'id',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Study programs retrieved successfully.',
            resourceClass: StudyProgramResource::class
        );
    }

    public function store(StudyProgramRequest $request): JsonResponse
    {
        $studyProgram = StudyProgram::create($request->validated());

        return $this->successResponse(
            data: new StudyProgramResource($studyProgram->load(['faculty', 'setting'])),
            message: 'Study program created successfully.',
            code: 201
        );
    }

    public function show(StudyProgram $studyProgram): JsonResponse
    {
        return $this->successResponse(
            data: new StudyProgramResource($studyProgram->load(['faculty.institution', 'setting'])),
            message: 'Study program retrieved successfully.'
        );
    }

    public function update(StudyProgramRequest $request, StudyProgram $studyProgram): JsonResponse
    {
        $studyProgram->update($request->validated());

        return $this->successResponse(
            data: new StudyProgramResource($studyProgram->load(['faculty', 'setting'])),
            message: 'Study program updated successfully.'
        );
    }

    public function updateSetting(Request $request, StudyProgram $studyProgram): JsonResponse
    {
        $validated = $request->validate([
            'min_gpa_graduation' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'min_final_exam_guidance' => ['nullable', 'integer', 'min:0'],
            'final_exam_advisors_count' => ['nullable', 'integer', 'min:1'],
            'final_exam_examiners_count' => ['nullable', 'integer', 'min:1'],
            'is_thesis_required' => ['nullable', 'boolean'],
        ]);

        $setting = $studyProgram->setting()->updateOrCreate(
            ['study_program_id' => $studyProgram->id],
            $validated
        );

        return $this->successResponse(
            data: new StudyProgramResource($studyProgram->load(['faculty', 'setting'])),
            message: 'Pengaturan program studi berhasil diperbarui.'
        );
    }

    public function destroy(StudyProgram $studyProgram): JsonResponse
    {
        $studyProgram->delete();

        return $this->successResponse(
            data: null,
            message: 'Study program deleted successfully.'
        );
    }
}
