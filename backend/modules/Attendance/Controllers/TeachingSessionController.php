<?php

namespace Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Models\TeachingSession;
use Modules\Attendance\Requests\TeachingSessionRequest;
use Modules\Attendance\Resources\TeachingSessionResource;
use Modules\Attendance\Services\AttendanceService;
use Modules\Class\Models\AcademicClass;

class TeachingSessionController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = TeachingSession::with(['academicClass.course', 'lecturer', 'room'])
            ->withCount('attendances');

        $user = $request->user();
        if ($user && $user->hasRole('dosen')) {
            $lecturer = $user->lecturer;
            if ($lecturer) {
                $query->where('lecturer_id', $lecturer->id);
            }
        }

        if ($request->filled('academic_class_id')) {
            $query->where('academic_class_id', $request->query('academic_class_id'));
        }

        if ($request->filled('lecturer_id')) {
            $query->where('lecturer_id', $request->query('lecturer_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('session_date', $request->query('date'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('topic', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('academicClass', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: [],
            filterableColumns: ['academic_class_id', 'lecturer_id', 'status', 'teaching_method'],
            defaultSort: 'session_date',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Teaching sessions retrieved successfully.',
            resourceClass: TeachingSessionResource::class
        );
    }

    public function store(TeachingSessionRequest $request): JsonResponse
    {
        $session = $this->attendanceService->createSession($request->validated());

        return $this->successResponse(
            data: new TeachingSessionResource($session),
            message: 'Teaching session created successfully.',
            code: 201
        );
    }

    public function show(TeachingSession $teaching_session): JsonResponse
    {
        $teaching_session->load(['academicClass.course', 'lecturer', 'room', 'attendances.student.studyProgram']);

        return $this->successResponse(
            data: new TeachingSessionResource($teaching_session),
            message: 'Teaching session retrieved successfully.'
        );
    }

    public function update(TeachingSessionRequest $request, TeachingSession $teaching_session): JsonResponse
    {
        $updated = $this->attendanceService->updateSession($teaching_session, $request->validated());

        return $this->successResponse(
            data: new TeachingSessionResource($updated),
            message: 'Teaching session updated successfully.'
        );
    }

    public function destroy(TeachingSession $teaching_session): JsonResponse
    {
        $teaching_session->delete();

        return $this->successResponse(
            data: null,
            message: 'Teaching session deleted successfully.'
        );
    }

    public function openCheckIn(Request $request, TeachingSession $teaching_session): JsonResponse
    {
        $duration = (int) $request->input('duration_minutes', 15);
        $updated = $this->attendanceService->openCheckIn($teaching_session, $duration);

        return $this->successResponse(
            data: new TeachingSessionResource($updated),
            message: 'Self check-in opened successfully.'
        );
    }

    public function closeSession(TeachingSession $teaching_session): JsonResponse
    {
        $updated = $this->attendanceService->closeSession($teaching_session);

        return $this->successResponse(
            data: new TeachingSessionResource($updated),
            message: 'Teaching session closed successfully.'
        );
    }

    public function classRecap(AcademicClass $class): JsonResponse
    {
        $recap = $this->attendanceService->getClassRecap($class);

        return $this->successResponse(
            data: $recap,
            message: 'Class attendance recap retrieved successfully.'
        );
    }
}
