<?php

namespace Modules\Student\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Student\Services\StudentPortalService;

class StudentPortalController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected StudentPortalService $portalService
    ) {}

    /**
     * Get authenticated student profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $student = $this->portalService->getStudentForUser($request->user());
        $profile = $this->portalService->getFullProfile($student);

        return $this->successResponse(
            data: $profile,
            message: 'Student profile retrieved successfully.'
        );
    }

    /**
     * Submit profile update request.
     */
    public function requestUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $student = $this->portalService->getStudentForUser($request->user());
        $updated = $this->portalService->requestProfileUpdate($student, $validated, $request->user());

        return $this->successResponse(
            data: $updated,
            message: 'Profile update request submitted successfully.'
        );
    }

    /**
     * Get student KHS (Kartu Hasil Studi) & Grades.
     */
    public function khs(Request $request): JsonResponse
    {
        $semesterId = $request->query('semester_id') ? (int) $request->query('semester_id') : null;
        $student = $this->portalService->getStudentForUser($request->user());
        $khsData = $this->portalService->getStudentKHS($student, $semesterId);

        return $this->successResponse(
            data: $khsData,
            message: 'Student KHS retrieved successfully.'
        );
    }

    /**
     * Get student weekly class schedules.
     */
    public function schedules(Request $request): JsonResponse
    {
        $student = $this->portalService->getStudentForUser($request->user());
        $schedules = $this->portalService->getStudentSchedules($student);

        return $this->successResponse(
            data: $schedules,
            message: 'Student class schedules retrieved successfully.'
        );
    }
}
