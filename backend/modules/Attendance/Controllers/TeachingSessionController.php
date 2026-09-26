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
use Modules\Attendance\Support\AttendanceAccess;
use Modules\Class\Models\AcademicClass;
use Modules\Schedule\Actions\SyncClassSessionsAction;

class TeachingSessionController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Daftar sesi. Tanpa scope di sini, `attendance.view` yang juga dipegang
     * mahasiswa akan memperlihatkan seluruh sesi institusi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TeachingSession::with(['academicClass.course', 'lecturer', 'room'])
            ->withCount('attendances');

        $query = AttendanceAccess::scopeSessionsFor($request->user(), $query);

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

    /**
     * Sesi susulan/ganti dibuat manual. Dosen hanya boleh membuat sesi atas nama
     * dirinya sendiri pada kelas yang ia ampu.
     */
    public function store(TeachingSessionRequest $request): JsonResponse
    {
        $user = $request->user();
        $lecturerId = (int) $request->input('lecturer_id');

        $allowed = AttendanceAccess::mayManageSessionsGlobally($user)
            || (AttendanceAccess::mayRecord($user)
                && $lecturerId === AttendanceAccess::ownLecturerId($user)
                && AttendanceAccess::teachesClass($lecturerId, (int) $request->input('academic_class_id')));

        if (!$allowed) {
            return $this->errorResponse('Hanya staf akademik atau dosen pengampu kelas ini yang dapat membuat sesi.', 403);
        }

        $session = $this->attendanceService->createSession($request->validated());

        return $this->successResponse(
            data: new TeachingSessionResource($session),
            message: 'Teaching session created successfully.',
            code: 201
        );
    }

    public function show(Request $request, TeachingSession $teaching_session): JsonResponse
    {
        if (!AttendanceAccess::maySeeSession($request->user(), $teaching_session)) {
            return $this->errorResponse('Anda tidak berwenang melihat sesi ini.', 403);
        }

        $teaching_session->load(['academicClass.course', 'lecturer', 'room', 'attendances.student.studyProgram']);

        return $this->successResponse(
            data: new TeachingSessionResource($teaching_session),
            message: 'Teaching session retrieved successfully.'
        );
    }

    public function update(TeachingSessionRequest $request, TeachingSession $teaching_session): JsonResponse
    {
        if (!AttendanceAccess::mayManageSession($request->user(), $teaching_session)) {
            return $this->errorResponse('Hanya dosen pengampu atau staf akademik yang dapat mengubah sesi ini.', 403);
        }

        $updated = $this->attendanceService->updateSession(
            $teaching_session,
            collect($request->validated())->except('correction_reason')->all(),
            $request->input('correction_reason')
        );

        return $this->successResponse(
            data: new TeachingSessionResource($updated),
            message: 'Teaching session updated successfully.'
        );
    }

    /**
     * Menghapus sesi = menghapus riwayat kehadiran yang menempel (cascade), jadi
     * dibatasi untuk staf dan ditolak bila presensinya sudah tercatat.
     */
    public function destroy(Request $request, TeachingSession $teaching_session): JsonResponse
    {
        if (!AttendanceAccess::mayManageSessionsGlobally($request->user())) {
            return $this->errorResponse('Hanya staf akademik yang dapat menghapus sesi perkuliahan.', 403);
        }

        $this->attendanceService->deleteSession($teaching_session);

        return $this->successResponse(
            data: null,
            message: 'Teaching session deleted successfully.'
        );
    }

    public function openCheckIn(Request $request, TeachingSession $teaching_session): JsonResponse
    {
        if (!AttendanceAccess::mayManageSession($request->user(), $teaching_session)) {
            return $this->errorResponse('Hanya dosen pengampu sesi ini yang dapat membuka presensi.', 403);
        }

        $duration = (int) $request->input('duration_minutes', 15);
        $updated = $this->attendanceService->openCheckIn($teaching_session, $duration);

        return $this->successResponse(
            data: new TeachingSessionResource($updated),
            message: 'Self check-in opened successfully.'
        );
    }

    public function closeSession(Request $request, TeachingSession $teaching_session): JsonResponse
    {
        if (!AttendanceAccess::mayManageSession($request->user(), $teaching_session)) {
            return $this->errorResponse('Hanya dosen pengampu sesi ini yang dapat menutupnya.', 403);
        }

        $updated = $this->attendanceService->closeSession($teaching_session);

        return $this->successResponse(
            data: new TeachingSessionResource($updated),
            message: 'Teaching session closed successfully.'
        );
    }

    /**
     * Membuka kembali sesi terkunci. Wajib alasan supaya jejak audit lengkap.
     */
    public function reopenSession(Request $request, TeachingSession $teaching_session): JsonResponse
    {
        if (!AttendanceAccess::mayManageSession($request->user(), $teaching_session)) {
            return $this->errorResponse('Hanya dosen pengampu atau staf akademik yang dapat membuka sesi ini.', 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $reopened = $this->attendanceService->reopenSession($teaching_session, $validated['reason']);

        return $this->successResponse(
            data: new TeachingSessionResource($reopened),
            message: 'Teaching session reopened successfully.'
        );
    }

    /**
     * Regenerasi sesi perkuliahan dari jadwal kelas (mis. jumlah minggu kuliah
     * berubah atau sesi kelas dengan dua jadwal per pekan perlu dinomori ulang).
     */
    public function syncSessions(Request $request, AcademicClass $class, SyncClassSessionsAction $syncer): JsonResponse
    {
        if (!AttendanceAccess::mayManageSessionsGlobally($request->user())) {
            return $this->errorResponse('Hanya staf akademik yang dapat menyinkronkan sesi kelas.', 403);
        }

        $affected = $syncer->syncClass($class);

        return $this->successResponse(
            data: ['class_id' => $class->id, 'affected_sessions' => $affected],
            message: "Sinkronisasi sesi selesai ({$affected} pertemuan dibuat/diperbarui)."
        );
    }

    public function classRecap(Request $request, AcademicClass $class): JsonResponse
    {
        if (!AttendanceAccess::maySeeClass($request->user(), $class)) {
            return $this->errorResponse('Anda tidak berwenang melihat rekap kelas ini.', 403);
        }

        $recap = $this->attendanceService->getClassRecap($class);

        return $this->successResponse(
            data: $recap,
            message: 'Class attendance recap retrieved successfully.'
        );
    }
}
