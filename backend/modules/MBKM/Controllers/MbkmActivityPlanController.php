<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\ActivityPlanStatus;
use Modules\MBKM\Models\MbkmActivityPlan;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Services\MbkmHistoryService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Activity plans: the planned activities of a participant during execution.
 */
class MbkmActivityPlanController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmHistoryService $historyService,
    ) {}

    public function index(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke aktivitas peserta ini.');
        }

        $plans = $participant->activityPlans()
            ->withCount('logs')
            ->orderBy('planned_start_date')
            ->get();

        return $this->successResponse($plans, 'Rencana aktivitas MBKM berhasil dimuat.');
    }

    public function store(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayWriteParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak menambah aktivitas peserta ini.');
        }

        $validated = $this->validatePayload($request);

        $plan = $participant->activityPlans()->create($validated);

        $this->historyService->record(
            entity: $participant,
            action: 'activity_plan.created',
            notes: 'Rencana aktivitas dibuat: ' . $plan->title,
            meta: ['activity_plan_id' => $plan->id],
            actorId: $request->user()?->id
        );

        return $this->successResponse($plan, 'Rencana aktivitas berhasil dibuat.', 201);
    }

    public function update(Request $request, MbkmActivityPlan $plan): JsonResponse
    {
        $plan->loadMissing('participant');

        if (!$this->mayWriteParticipant($request, $plan->participant)) {
            return $this->mbkmDeny('Anda tidak berhak mengubah aktivitas peserta ini.');
        }

        $plan->update($this->validatePayload($request, $plan));

        return $this->successResponse($plan->fresh(), 'Rencana aktivitas berhasil diperbarui.');
    }

    public function destroy(Request $request, MbkmActivityPlan $plan): JsonResponse
    {
        $plan->loadMissing('participant');

        if (!$this->mayWriteParticipant($request, $plan->participant)) {
            return $this->mbkmDeny('Anda tidak berhak menghapus aktivitas peserta ini.');
        }

        $plan->delete();

        return $this->successResponse(null, 'Rencana aktivitas berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, ?MbkmActivityPlan $plan = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_output' => ['nullable', 'string'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'planned_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ActivityPlanStatus::values())],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
