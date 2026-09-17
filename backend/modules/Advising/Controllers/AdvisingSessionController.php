<?php

namespace Modules\Advising\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use App\Support\Traits\ScopesToOwnLecturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Advising\Models\AdvisingSession;
use Modules\Advising\Requests\CreateAdvisingSessionRequest;
use Modules\Advising\Requests\UpdateAdvisingSessionRequest;
use Modules\Advising\Resources\AdvisingSessionResource;
use Modules\Advising\Services\AdvisingService;

class AdvisingSessionController extends Controller
{
    use HasApiResponse, ScopesToOwnLecturer;

    public function __construct(
        protected AdvisingService $advisingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = AdvisingSession::with(['student.studyProgram', 'lecturer', 'enrollment.semester']);

        $user = $request->user();
        if ($user && $user->hasRole('mahasiswa')) {
            $student = $user->student;
            if (!$student) {
                return $this->errorResponse('Student profile not found.', 404);
            }
            $query->where('student_id', $student->id);
        }

        // A plain lecturer only sees the consultations they logged.
        $this->scopeToOwnLecturer($request, $query, 'advising.assign', null, 'lecturer_id');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('topic', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('lecturer', function ($lq) use ($search) {
                      $lq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nidn', 'like', "%{$search}%");
                  });
            });
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: [],
            filterableColumns: ['student_id', 'lecturer_id', 'enrollment_id', 'status'],
            defaultSort: 'session_date',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Advising sessions retrieved successfully.',
            resourceClass: AdvisingSessionResource::class
        );
    }

    public function store(CreateAdvisingSessionRequest $request): JsonResponse
    {
        $data = $request->validated();

        // A plain lecturer may only log consultations under their own name.
        [$isLecturerOnly, $ownLecturerId] = $this->resolveOwnLecturerScope($request, 'advising.assign');
        if ($isLecturerOnly) {
            if (!$ownLecturerId) {
                return $this->errorResponse('Lecturer profile not found.', 404);
            }

            if ((int) $data['lecturer_id'] !== $ownLecturerId) {
                return $this->errorResponse('Unauthorized to log an advising session for another lecturer.', 403);
            }

            $data['lecturer_id'] = $ownLecturerId;
        }

        $session = $this->advisingService->createSession($data);

        return $this->successResponse(
            data: new AdvisingSessionResource($session),
            message: 'Advising session logged successfully.',
            code: 201
        );
    }

    public function show(Request $request, AdvisingSession $session): JsonResponse
    {
        if (!$this->lecturerMayAccessOwnedRecord($request, $session->lecturer_id, 'advising.assign')) {
            return $this->errorResponse('Unauthorized to view this advising session.', 403);
        }

        return $this->successResponse(
            data: new AdvisingSessionResource($session->load(['student.studyProgram', 'lecturer', 'enrollment.semester'])),
            message: 'Advising session retrieved successfully.'
        );
    }

    public function update(UpdateAdvisingSessionRequest $request, AdvisingSession $session): JsonResponse
    {
        if (!$this->lecturerMayAccessOwnedRecord($request, $session->lecturer_id, 'advising.assign')) {
            return $this->errorResponse('Unauthorized to update this advising session.', 403);
        }

        $updated = $this->advisingService->updateSession($session, $request->validated());

        return $this->successResponse(
            data: new AdvisingSessionResource($updated),
            message: 'Advising session updated successfully.'
        );
    }

    public function destroy(Request $request, AdvisingSession $session): JsonResponse
    {
        if (!$this->lecturerMayAccessOwnedRecord($request, $session->lecturer_id, 'advising.assign')) {
            return $this->errorResponse('Unauthorized to delete this advising session.', 403);
        }

        $session->delete();

        return $this->successResponse(
            data: null,
            message: 'Advising session deleted successfully.'
        );
    }
}
