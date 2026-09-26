<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\LearningAgreementStatus;
use Modules\MBKM\Models\MbkmLearningAgreement;
use Modules\MBKM\Models\MbkmLearningAgreementItem;
use Modules\MBKM\Models\MbkmParticipant;

/**
 * Learning agreement / recognition plan workflow:
 * draft -> submitted -> reviewed -> approved -> locked.
 *
 * Once approved or locked the document is frozen: the regular edit endpoints
 * refuse changes and a formal revision (re-opening) is required instead.
 */
class MbkmLearningAgreementService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
    ) {}

    public function createOrUpdate(MbkmParticipant $participant, array $data, User $actor): MbkmLearningAgreement
    {
        return DB::transaction(function () use ($participant, $data, $actor) {
            $agreement = MbkmLearningAgreement::firstOrNew(['participant_id' => $participant->id]);

            if ($agreement->exists && $agreement->isLocked()) {
                throw ValidationException::withMessages([
                    'status' => ['Learning agreement yang sudah disetujui/terkunci tidak dapat diubah.'],
                ]);
            }

            $isNew = !$agreement->exists;
            $from = $agreement->exists
                ? ($agreement->status instanceof LearningAgreementStatus ? $agreement->status->value : (string) $agreement->status)
                : null;

            $agreement->fill([
                'title' => $data['title'] ?? $agreement->title ?? 'Learning Agreement MBKM',
                'period_start' => $data['period_start'] ?? $agreement->period_start ?? $participant->start_date,
                'period_end' => $data['period_end'] ?? $agreement->period_end ?? $participant->end_date,
                'notes' => $data['notes'] ?? $agreement->notes,
                'status' => $agreement->exists ? $agreement->status : LearningAgreementStatus::DRAFT,
            ]);

            $agreement->save();

            if (isset($data['items']) && is_array($data['items'])) {
                $agreement->items()->delete();

                foreach (array_values($data['items']) as $index => $item) {
                    MbkmLearningAgreementItem::create([
                        'learning_agreement_id' => $agreement->id,
                        'activity_title' => $item['activity_title'],
                        'activity_description' => $item['activity_description'] ?? null,
                        'course_id' => $item['course_id'] ?? null,
                        'curriculum_subject_id' => $item['curriculum_subject_id'] ?? null,
                        'credits' => $item['credits'] ?? 0,
                        'target_grade' => $item['target_grade'] ?? null,
                        'sort_order' => $item['sort_order'] ?? $index,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            }

            $this->historyService->record(
                entity: $agreement,
                action: $isNew ? 'learning_agreement.created' : 'learning_agreement.updated',
                fromStatus: $from,
                toStatus: $agreement->status instanceof LearningAgreementStatus
                    ? $agreement->status->value
                    : (string) $agreement->status,
                notes: 'Learning agreement disimpan.',
                meta: ['total_credits' => $agreement->totalCredits()],
                actorId: $actor->id
            );

            return $agreement->fresh(['items.course']);
        });
    }

    public function transition(
        MbkmLearningAgreement $agreement,
        string $target,
        User $actor,
        ?string $notes = null
    ): MbkmLearningAgreement {
        $current = $agreement->status instanceof LearningAgreementStatus
            ? $agreement->status
            : LearningAgreementStatus::from((string) $agreement->status);

        $allowed = match ($current) {
            LearningAgreementStatus::DRAFT => [LearningAgreementStatus::SUBMITTED],
            LearningAgreementStatus::SUBMITTED => [LearningAgreementStatus::REVIEWED, LearningAgreementStatus::DRAFT],
            LearningAgreementStatus::REVIEWED => [LearningAgreementStatus::APPROVED, LearningAgreementStatus::SUBMITTED],
            LearningAgreementStatus::APPROVED => [LearningAgreementStatus::LOCKED],
            LearningAgreementStatus::LOCKED => [],
        };

        $targetEnum = LearningAgreementStatus::from($target);

        if (!in_array($targetEnum, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Transisi dari {$current->value} ke {$target} tidak diizinkan."],
            ]);
        }

        if ($targetEnum === LearningAgreementStatus::SUBMITTED && $agreement->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Learning agreement harus memiliki minimal satu rencana aktivitas/mata kuliah.'],
            ]);
        }

        $payload = ['status' => $targetEnum];

        if ($targetEnum === LearningAgreementStatus::SUBMITTED) {
            $payload['submitted_at'] = now();
        }

        if ($targetEnum === LearningAgreementStatus::REVIEWED) {
            $payload['reviewed_at'] = now();
            $payload['reviewed_by'] = $actor->id;
            $payload['review_notes'] = $notes;
        }

        if ($targetEnum === LearningAgreementStatus::APPROVED) {
            $payload['approved_at'] = now();
            $payload['approved_by'] = $actor->id;
        }

        if ($targetEnum === LearningAgreementStatus::LOCKED) {
            $payload['locked_at'] = now();
        }

        $agreement->update($payload);

        $this->historyService->record(
            entity: $agreement,
            action: 'learning_agreement.' . $targetEnum->value,
            fromStatus: $current->value,
            toStatus: $targetEnum->value,
            notes: $notes,
            actorId: $actor->id
        );

        if (in_array($targetEnum, [LearningAgreementStatus::APPROVED, LearningAgreementStatus::LOCKED], true)) {
            $agreement->loadMissing('participant.student');
            $this->notificationService->notifyStudent(
                $agreement->participant?->student,
                'learning_agreement.approved',
                'Learning Agreement Disetujui',
                'Rencana rekognisi MBKM Anda telah disetujui.',
            );
        }

        return $agreement->fresh(['items.course']);
    }

    /**
     * Re-open a locked agreement through an explicit revision (keeps the history).
     */
    public function reopenForRevision(MbkmLearningAgreement $agreement, User $actor, string $reason): MbkmLearningAgreement
    {
        if (!$agreement->isLocked()) {
            throw ValidationException::withMessages([
                'status' => ['Hanya learning agreement yang terkunci dapat dibuka kembali untuk revisi.'],
            ]);
        }

        $from = $agreement->status instanceof LearningAgreementStatus
            ? $agreement->status->value
            : (string) $agreement->status;

        $agreement->update([
            'status' => LearningAgreementStatus::DRAFT,
            'locked_at' => null,
            'review_notes' => $reason,
        ]);

        $this->historyService->record(
            entity: $agreement,
            action: 'learning_agreement.reopened',
            fromStatus: $from,
            toStatus: LearningAgreementStatus::DRAFT->value,
            notes: $reason,
            actorId: $actor->id
        );

        return $agreement->fresh(['items.course']);
    }
}
