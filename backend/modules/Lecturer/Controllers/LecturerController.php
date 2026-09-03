<?php

namespace Modules\Lecturer\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Lecturer\Models\Lecturer;
use Modules\Lecturer\Requests\ChangeLecturerStatusRequest;
use Modules\Lecturer\Requests\CreateLecturerRequest;
use Modules\Lecturer\Requests\UpdateLecturerRequest;
use Modules\Lecturer\Resources\LecturerResource;
use Modules\Lecturer\Services\LecturerService;

class LecturerController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected LecturerService $lecturerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Lecturer::with(['homebaseStudyProgram.faculty', 'user']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['full_name', 'nidn', 'nip', 'email'],
            filterableColumns: ['status', 'homebase_study_program_id', 'gender', 'functional_position'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Lecturers retrieved successfully.',
            resourceClass: LecturerResource::class
        );
    }

    public function store(CreateLecturerRequest $request): JsonResponse
    {
        $lecturer = $this->lecturerService->create($request->validated());

        return $this->successResponse(
            data: new LecturerResource($lecturer),
            message: 'Lecturer created successfully.',
            code: 201
        );
    }

    public function show(Lecturer $lecturer): JsonResponse
    {
        return $this->successResponse(
            data: new LecturerResource($lecturer->load(['homebaseStudyProgram.faculty.institution', 'educations', 'expertises', 'user'])),
            message: 'Lecturer retrieved successfully.'
        );
    }

    public function update(UpdateLecturerRequest $request, Lecturer $lecturer): JsonResponse
    {
        $updatedLecturer = $this->lecturerService->update($lecturer, $request->validated());

        return $this->successResponse(
            data: new LecturerResource($updatedLecturer),
            message: 'Lecturer updated successfully.'
        );
    }

    public function destroy(Request $request, Lecturer $lecturer): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('lecturers.delete')) {
            return $this->errorResponse('Unauthorized to delete lecturer.', 403);
        }

        $lecturer->delete();

        return $this->successResponse(
            data: null,
            message: 'Lecturer deleted successfully.'
        );
    }

    public function changeStatus(ChangeLecturerStatusRequest $request, Lecturer $lecturer): JsonResponse
    {
        $updated = $this->lecturerService->changeStatus(
            lecturer: $lecturer,
            status: $request->validated('status'),
            notes: $request->validated('notes')
        );

        return $this->successResponse(
            data: new LecturerResource($updated->fresh(['homebaseStudyProgram.faculty'])),
            message: 'Lecturer status updated successfully.'
        );
    }

    public function updateQuotas(Request $request, Lecturer $lecturer): JsonResponse
    {
        $validated = $request->validate([
            'academic_advising_quota' => ['required', 'integer', 'min:0', 'max:100'],
            'thesis_supervisor_quota' => ['required', 'integer', 'min:0', 'max:100'],
            'thesis_examiner_quota' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $lecturer->update($validated);

        return $this->successResponse(
            data: new LecturerResource($lecturer->fresh(['homebaseStudyProgram.faculty'])),
            message: 'Kuota pembimbing dosen berhasil diperbarui.'
        );
    }
}
