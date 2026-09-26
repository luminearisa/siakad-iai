<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\LearningAgreementStatus;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Services\MbkmLearningAgreementService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Learning agreement / recognition plan for a participant.
 * Workflow: draft -> submitted -> reviewed -> approved -> locked.
 */
class MbkmLearningAgreementController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmLearningAgreementService $service,
    ) {}

    public function show(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke learning agreement peserta ini.');
        }

        $agreement = $participant->learningAgreement()
            ->with(['items.course', 'documents'])
            ->first();

        return $this->successResponse([
            'learning_agreement' => $agreement,
            'total_credits' => $agreement?->totalCredits() ?? 0,
            'is_locked' => $agreement?->isLocked() ?? false,
        ], 'Learning agreement peserta MBKM.');
    }

    /**
     * Create or update the agreement (student or manager).
     */
    public function upsert(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayWriteParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak menyusun learning agreement peserta ini.');
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.activity_title' => ['required_with:items', 'string', 'max:255'],
            'items.*.activity_description' => ['nullable', 'string'],
            'items.*.course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'items.*.curriculum_subject_id' => ['nullable', 'integer', 'exists:curriculum_subjects,id'],
            'items.*.credits' => ['nullable', 'integer', 'min:0', 'max:24'],
            'items.*.target_grade' => ['nullable', 'string', 'max:5'],
            'items.*.sort_order' => ['nullable', 'integer'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $agreement = $this->service->createOrUpdate($participant, $validated, $request->user());

        return $this->successResponse($agreement, 'Learning agreement berhasil disimpan.');
    }

    /**
     * Workflow transition.
     */
    public function transition(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke learning agreement peserta ini.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(LearningAgreementStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $agreement = $participant->learningAgreement;

        if (!$agreement) {
            return $this->errorResponse('Learning agreement belum dibuat.', 404);
        }

        $updated = $this->service->transition($agreement, $validated['status'], $request->user(), $validated['notes'] ?? null);

        return $this->successResponse($updated, 'Status learning agreement berhasil diperbarui.');
    }

    /**
     * Re-open a locked agreement for a formal revision (keeps the audit trail).
     */
    public function reopen(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak membuka kembali learning agreement peserta ini.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $agreement = $participant->learningAgreement;

        if (!$agreement) {
            return $this->errorResponse('Learning agreement belum dibuat.', 404);
        }

        $updated = $this->service->reopenForRevision($agreement, $request->user(), $validated['reason']);

        return $this->successResponse($updated, 'Learning agreement dibuka kembali untuk revisi.');
    }
}
