<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MBKM\Services\MbkmDashboardService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Role dashboards: mahasiswa, dosen/pembimbing, and admin MBKM/akademik.
 */
class MbkmDashboardController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmDashboardService $dashboardService,
    ) {}

    /**
     * Dashboard for the authenticated user, resolved by role.
     */
    public function index(Request $request): JsonResponse
    {
        if ($this->isMbkmStudent($request)) {
            $student = $this->requireStudent($request);

            return $this->successResponse(
                ['role' => 'mahasiswa', ...$this->dashboardService->studentDashboard($student)],
                'Dashboard MBKM mahasiswa.'
            );
        }

        if ($this->isMbkmLecturerOnly($request)) {
            $lecturer = $this->currentLecturer($request);

            if (!$lecturer) {
                return $this->successResponse(['role' => 'dosen', 'counters' => []], 'Dashboard MBKM dosen.');
            }

            return $this->successResponse(
                ['role' => 'dosen', ...$this->dashboardService->lecturerDashboard($lecturer)],
                'Dashboard MBKM dosen.'
            );
        }

        // Falling through to the admin dashboard was the default for *any*
        // authenticated user who is neither a student nor a plain lecturer —
        // including `admin_akademik`, which holds no mbkm.* permission at all.
        // This endpoint has no permission middleware, so the fallback itself has
        // to prove the caller is a module manager.
        if (!$this->isMbkmManager($request)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke dashboard MBKM.');
        }

        return $this->successResponse(
            ['role' => 'admin', ...$this->dashboardService->adminDashboard($request)],
            'Dashboard MBKM admin.'
        );
    }

    /**
     * Explicit admin dashboard endpoint (same filters as the index).
     */
    public function admin(Request $request): JsonResponse
    {
        if (!$this->isMbkmManager($request)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke dashboard MBKM.');
        }

        return $this->successResponse(
            ['role' => 'admin', ...$this->dashboardService->adminDashboard($request)],
            'Dashboard MBKM admin.'
        );
    }

    public function lecturer(Request $request): JsonResponse
    {
        $lecturer = $this->currentLecturer($request);

        if (!$lecturer) {
            return $this->errorResponse('Profil dosen tidak ditemukan.', 404);
        }

        return $this->successResponse(
            ['role' => 'dosen', ...$this->dashboardService->lecturerDashboard($lecturer)],
            'Dashboard MBKM dosen.'
        );
    }

    public function student(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);

        return $this->successResponse(
            ['role' => 'mahasiswa', ...$this->dashboardService->studentDashboard($student)],
            'Dashboard MBKM mahasiswa.'
        );
    }
}
