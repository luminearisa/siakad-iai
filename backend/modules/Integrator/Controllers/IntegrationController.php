<?php

namespace Modules\Integrator\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrator\Enums\ApiKeyScope;
use Modules\Integrator\Models\ApiKey;
use Modules\Integrator\Requests\IntegrationQueryRequest;
use Modules\Integrator\Services\IntegratorDataService;

/**
 * Read-only integration API consumed by external systems (the first of which is the
 * standalone `integrator/` application that bridges SIAKAD to Neo Feeder PDDikti).
 *
 * Authentication is an API key with explicit scopes; authorisation is enforced per
 * route by the `api.key:<scope>` middleware, and every response is logged in
 * `api_request_logs`. Nothing here writes to SIAKAD.
 */
class IntegrationController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected IntegratorDataService $dataService
    ) {}

    /**
     * Health probe: confirms the credential works and shows the granted scopes.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var ApiKey $key */
        $key = $request->attributes->get('integrator.api_key');

        return $this->successResponse(
            data: [
                ...$this->dataService->ping(),
                'client' => [
                    'name' => $key->client?->name,
                    'slug' => $key->client?->slug,
                ],
                'key' => [
                    'prefix' => $key->key_prefix,
                    'scopes' => $key->scopeList(),
                    'expires_at' => $key->expires_at?->toIso8601String(),
                ],
            ],
            message: 'Integration API is reachable.'
        );
    }

    /**
     * Institutional profile, study programs, periods and grade scales.
     */
    public function profile(): JsonResponse
    {
        return $this->successResponse(
            data: $this->dataService->profile(),
            message: 'Institutional profile retrieved successfully.'
        );
    }

    /**
     * Period structure with PDDikti semester codes.
     */
    public function semesters(): JsonResponse
    {
        return $this->successResponse(
            data: $this->dataService->semesterList(),
            message: 'Semesters retrieved successfully.'
        );
    }

    /**
     * Students, maskable PII, incremental by `updated_since`.
     */
    public function students(IntegrationQueryRequest $request): JsonResponse
    {
        return $this->paginatedResponse(
            paginator: $this->dataService->students($request),
            message: 'Students retrieved successfully.'
        );
    }

    /**
     * One student with academic snapshot, registrations, family and school history.
     */
    public function student(IntegrationQueryRequest $request, string $studentNumber): JsonResponse
    {
        $key = $request->attributes->get('integrator.api_key');
        $includePii = $key instanceof ApiKey && $key->can(ApiKeyScope::STUDENTS_PII);

        $student = $this->dataService->student($studentNumber, $includePii);

        if (! $student) {
            return $this->errorResponse(
                message: 'Student not found.',
                code: 404
            );
        }

        return $this->successResponse(
            data: $student,
            message: 'Student retrieved successfully.'
        );
    }

    /**
     * Lecturers with their home base study program.
     */
    public function lecturers(IntegrationQueryRequest $request): JsonResponse
    {
        return $this->paginatedResponse(
            paginator: $this->dataService->lecturers($request),
            message: 'Lecturers retrieved successfully.'
        );
    }

    /**
     * Course catalogue.
     */
    public function courses(IntegrationQueryRequest $request): JsonResponse
    {
        return $this->paginatedResponse(
            paginator: $this->dataService->courses($request),
            message: 'Courses retrieved successfully.'
        );
    }

    /**
     * Curricula with subjects per semester.
     */
    public function curricula(IntegrationQueryRequest $request): JsonResponse
    {
        return $this->paginatedResponse(
            paginator: $this->dataService->curricula($request),
            message: 'Curricula retrieved successfully.'
        );
    }

    /**
     * Classes for a semester, including lecturers and schedules.
     */
    public function classes(IntegrationQueryRequest $request): JsonResponse
    {
        return $this->paginatedResponse(
            paginator: $this->dataService->classes($request),
            message: 'Classes retrieved successfully.'
        );
    }

    /**
     * KRS rows (peserta kelas) per semester.
     */
    public function enrollments(IntegrationQueryRequest $request): JsonResponse
    {
        return $this->paginatedResponse(
            paginator: $this->dataService->enrollments($request),
            message: 'Enrollments retrieved successfully.'
        );
    }

    /**
     * AKM rows (SKS + IPS per student per semester).
     */
    public function akm(IntegrationQueryRequest $request): JsonResponse
    {
        $paginator = $this->dataService->akm($request);

        if (! $paginator) {
            return $this->errorResponse(
                message: 'Parameter semester_id wajib diisi untuk data AKM.',
                code: 422
            );
        }

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'AKM retrieved successfully.'
        );
    }

    /**
     * Grade recap, either per class or per student.
     */
    public function grades(IntegrationQueryRequest $request): JsonResponse
    {
        $paginator = $this->dataService->grades($request);

        if (! $paginator) {
            return $this->errorResponse(
                message: 'Parameter student_number atau class_id wajib diisi.',
                code: 422
            );
        }

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Grades retrieved successfully.'
        );
    }

    /**
     * Thesis and MBKM activities (`?type=thesis|mbkm`).
     */
    public function activities(IntegrationQueryRequest $request): JsonResponse
    {
        $type = (string) $request->query('type');

        if (! in_array($type, ['thesis', 'mbkm'], true)) {
            return $this->errorResponse(
                message: 'Parameter type wajib diisi dengan nilai thesis atau mbkm.',
                code: 422
            );
        }

        return $this->paginatedResponse(
            paginator: $this->dataService->activities($request, $type),
            message: ucfirst($type).' activities retrieved successfully.'
        );
    }

    /**
     * Graduates / drop-outs from yudisium.
     */
    public function graduates(IntegrationQueryRequest $request): JsonResponse
    {
        return $this->paginatedResponse(
            paginator: $this->dataService->graduates($request),
            message: 'Graduates retrieved successfully.'
        );
    }

    /**
     * Counters for the integrator dashboard.
     */
    public function snapshot(IntegrationQueryRequest $request): JsonResponse
    {
        return $this->successResponse(
            data: $this->dataService->snapshot(
                $request->query('semester_id') ? (int) $request->query('semester_id') : null
            ),
            message: 'Snapshot retrieved successfully.'
        );
    }
}
