<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\LogbookStatus;
use Modules\MBKM\Models\MbkmActivityLog;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Services\MbkmLogbookService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Activity log / logbook: student entry, submission, supervisor review.
 */
class MbkmLogbookController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmLogbookService $service,
    ) {}

    public function index(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke logbook peserta ini.');
        }

        $query = $participant->activityLogs()->with(['activityPlan', 'reviewer:id,name', 'documents']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['activity', 'description', 'period_label'],
            filterableColumns: [],
            defaultSort: 'log_date',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Logbook MBKM berhasil dimuat.');
    }

    /**
     * Cross-participant logbook list for supervisors / admins.
     */
    public function all(Request $request): JsonResponse
    {
        $query = MbkmActivityLog::query()->with(['participant.program', 'participant.student', 'reviewer:id,name']);

        // Role-branch scoping left the study-program-scoped manager (kaprodi)
        // unscoped: a kaprodi is also a `dosen`, but `isMbkmLecturerOnly()` is
        // false for them, so no branch applied and they saw every logbook.
        $visible = $this->visibleStudentIds($request);

        if ($visible !== null) {
            $query->whereHas('participant', fn ($q) => $q->whereIn('student_id', $visible));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('program_id')) {
            $query->whereHas('participant', fn ($q) => $q->where('program_id', $request->query('program_id')));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['activity', 'description'],
            filterableColumns: [],
            defaultSort: 'log_date',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Daftar logbook MBKM berhasil dimuat.');
    }

    public function store(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayWriteParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak menambah logbook peserta ini.');
        }

        $validated = $this->validatePayload($request);

        $log = $this->service->create($participant, $validated, $request->user());

        return $this->successResponse($log, 'Logbook berhasil dibuat.', 201);
    }

    public function update(Request $request, MbkmActivityLog $logbook): JsonResponse
    {
        $logbook->loadMissing('participant');

        if (!$this->mayWriteParticipant($request, $logbook->participant)) {
            return $this->mbkmDeny('Anda tidak berhak mengubah logbook ini.');
        }

        $updated = $this->service->update($logbook, $this->validatePayload($request), $request->user());

        return $this->successResponse($updated, 'Logbook berhasil diperbarui.');
    }

    public function submit(Request $request, MbkmActivityLog $logbook): JsonResponse
    {
        $logbook->loadMissing('participant');

        if (!$this->mayWriteParticipant($request, $logbook->participant)) {
            return $this->mbkmDeny('Anda tidak berhak mengajukan logbook ini.');
        }

        $submitted = $this->service->submit($logbook, $request->user());

        return $this->successResponse($submitted, 'Logbook berhasil diajukan.');
    }

    /**
     * Supervisor review.
     */
    public function review(Request $request, MbkmActivityLog $logbook): JsonResponse
    {
        $logbook->loadMissing('participant');

        if (!$this->mayManageParticipant($request, $logbook->participant)) {
            return $this->mbkmDeny('Anda tidak berhak mereview logbook ini.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in([
                LogbookStatus::APPROVED->value,
                LogbookStatus::REVISION_REQUIRED->value,
                LogbookStatus::REJECTED->value,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reviewed = $this->service->review($logbook, $request->user(), $validated['decision'], $validated['notes'] ?? null);

        return $this->successResponse($reviewed, 'Review logbook berhasil disimpan.');
    }

    public function destroy(Request $request, MbkmActivityLog $logbook): JsonResponse
    {
        $logbook->loadMissing('participant');

        if (!$this->mayWriteParticipant($request, $logbook->participant)) {
            return $this->mbkmDeny('Anda tidak berhak menghapus logbook ini.');
        }

        if ($logbook->isFinalized()) {
            return $this->errorResponse('Logbook yang sudah difinalisasi tidak dapat dihapus.', 422);
        }

        $logbook->delete();

        return $this->successResponse(null, 'Logbook berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'activity_plan_id' => ['nullable', 'integer', 'exists:mbkm_activity_plans,id'],
            'log_date' => ['required', 'date'],
            'period_label' => ['nullable', 'string', 'max:50'],
            'activity' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'output' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
