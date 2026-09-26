<?php

namespace Modules\MBKM\Services;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\LogbookStatus;
use Modules\MBKM\Models\MbkmActivityLog;
use Modules\MBKM\Models\MbkmParticipant;

/**
 * Logbook (activity log) workflow: draft -> submitted -> revision_required |
 * approved | rejected. Finalized entries are frozen; the student may no longer
 * edit them through the normal endpoints.
 */
class MbkmLogbookService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
    ) {}

    public function create(MbkmParticipant $participant, array $data, User $actor): MbkmActivityLog
    {
        $this->assertParticipantIsExecuting($participant);

        $log = $participant->activityLogs()->create([
            'activity_plan_id' => $data['activity_plan_id'] ?? null,
            'log_date' => $data['log_date'],
            'period_label' => $data['period_label'] ?? null,
            'activity' => $data['activity'],
            'description' => $data['description'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'output' => $data['output'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => LogbookStatus::DRAFT,
        ]);

        $this->historyService->record(
            entity: $log,
            action: 'logbook.created',
            toStatus: LogbookStatus::DRAFT->value,
            notes: 'Logbook dibuat.',
            actorId: $actor->id
        );

        return $log;
    }

    /**
     * Update a logbook entry. Refuses once the entry is finalized.
     */
    public function update(MbkmActivityLog $log, array $data, User $actor): MbkmActivityLog
    {
        if ($log->isFinalized()) {
            throw ValidationException::withMessages([
                'status' => ['Logbook yang sudah difinalisasi tidak dapat diubah melalui endpoint biasa.'],
            ]);
        }

        $from = $log->status instanceof LogbookStatus ? $log->status->value : (string) $log->status;

        $log->update([
            'activity_plan_id' => $data['activity_plan_id'] ?? $log->activity_plan_id,
            'log_date' => $data['log_date'] ?? $log->log_date,
            'period_label' => $data['period_label'] ?? $log->period_label,
            'activity' => $data['activity'] ?? $log->activity,
            'description' => $data['description'] ?? $log->description,
            'duration_hours' => $data['duration_hours'] ?? $log->duration_hours,
            'output' => $data['output'] ?? $log->output,
            'location' => $data['location'] ?? $log->location,
            // Editing a revision-required entry moves it back to draft.
            'status' => $from === LogbookStatus::REVISION_REQUIRED->value ? LogbookStatus::DRAFT : $log->status,
        ]);

        $this->historyService->record(
            entity: $log,
            action: 'logbook.updated',
            fromStatus: $from,
            toStatus: $log->status instanceof LogbookStatus ? $log->status->value : (string) $log->status,
            notes: 'Logbook diperbarui.',
            actorId: $actor->id
        );

        return $log->fresh();
    }

    public function submit(MbkmActivityLog $log, User $actor): MbkmActivityLog
    {
        $from = $log->status instanceof LogbookStatus ? $log->status->value : (string) $log->status;

        if ($log->isFinalized()) {
            throw ValidationException::withMessages([
                'status' => ['Logbook ini sudah difinalisasi.'],
            ]);
        }

        $log->update([
            'status' => LogbookStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->historyService->record(
            entity: $log,
            action: 'logbook.submitted',
            fromStatus: $from,
            toStatus: LogbookStatus::SUBMITTED->value,
            notes: 'Logbook diajukan untuk verifikasi.',
            actorId: $actor->id
        );

        $log->loadMissing('participant.student');
        $this->notificationService->notifyParticipantSupervisors(
            $log->participant,
            'logbook.submitted',
            'Logbook MBKM Baru',
            'Logbook baru dari ' . ($log->participant?->student?->full_name ?? 'mahasiswa') . ' menunggu verifikasi.',
            ['logbook_id' => $log->id]
        );

        return $log->fresh();
    }

    /**
     * Supervisor review: approve, request revision, or reject.
     *
     * Only a *submitted* entry may be reviewed, and an already-finalized entry
     * may never be re-reviewed — otherwise a supervisor could push an approved
     * (locked) logbook back to `revision_required`, which would unlock it for
     * the student to edit through the normal update endpoint.
     */
    public function review(MbkmActivityLog $log, User $reviewer, string $decision, ?string $notes = null): MbkmActivityLog
    {
        if ($log->isFinalized()) {
            throw ValidationException::withMessages([
                'status' => ['Logbook yang sudah difinalisasi tidak dapat ditinjau ulang.'],
            ]);
        }

        $allowed = [
            LogbookStatus::APPROVED->value,
            LogbookStatus::REVISION_REQUIRED->value,
            LogbookStatus::REJECTED->value,
        ];

        if (!in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages([
                'decision' => ['Keputusan review logbook tidak valid.'],
            ]);
        }

        $from = $log->status instanceof LogbookStatus ? $log->status->value : (string) $log->status;

        if ($from !== LogbookStatus::SUBMITTED->value) {
            throw ValidationException::withMessages([
                'status' => ['Hanya logbook yang sudah diajukan mahasiswa yang dapat ditinjau.'],
            ]);
        }

        $log->update([
            'status' => $decision,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'review_notes' => $notes,
            'revision_count' => $decision === LogbookStatus::REVISION_REQUIRED->value
                ? $log->revision_count + 1
                : $log->revision_count,
            'locked_at' => $decision === LogbookStatus::APPROVED->value ? now() : $log->locked_at,
        ]);

        $this->historyService->record(
            entity: $log,
            action: 'logbook.reviewed',
            fromStatus: $from,
            toStatus: $decision,
            notes: $notes,
            actorId: $reviewer->id
        );

        $log->loadMissing('participant.student');
        $messages = [
            LogbookStatus::APPROVED->value => ['Logbook MBKM Disetujui', 'Logbook Anda telah disetujui pembimbing.'],
            LogbookStatus::REVISION_REQUIRED->value => ['Logbook MBKM Perlu Revisi', 'Logbook Anda perlu diperbaiki: ' . ($notes ?? '-')],
            LogbookStatus::REJECTED->value => ['Logbook MBKM Ditolak', 'Logbook Anda ditolak: ' . ($notes ?? '-')],
        ];

        [$title, $message] = $messages[$decision];

        $this->notificationService->notifyStudent(
            $log->participant?->student,
            'logbook.' . $decision,
            $title,
            $message,
            ['logbook_id' => $log->id]
        );

        return $log->fresh();
    }

    /**
     * Logbook progress summary for a participant.
     *
     * @return array<string, int|float|null>
     */
    public function summary(MbkmParticipant $participant): array
    {
        $logs = $participant->activityLogs()->get();

        $approved = $logs->where('status', LogbookStatus::APPROVED)->count();
        $submitted = $logs->where('status', LogbookStatus::SUBMITTED)->count();
        $revision = $logs->where('status', LogbookStatus::REVISION_REQUIRED)->count();
        $draft = $logs->where('status', LogbookStatus::DRAFT)->count();

        return [
            'total' => $logs->count(),
            'approved' => $approved,
            'submitted' => $submitted,
            'revision_required' => $revision,
            'draft' => $draft,
            'total_hours' => round((float) $logs->sum('duration_hours'), 2),
            'progress_percentage' => $logs->count() > 0 ? round(($approved / $logs->count()) * 100, 2) : 0.0,
        ];
    }

    protected function assertParticipantIsExecuting(MbkmParticipant $participant): void
    {
        $status = $participant->status instanceof \Modules\MBKM\Enums\ParticipantStatus
            ? $participant->status->value
            : (string) $participant->status;

        if (!in_array($status, [
            \Modules\MBKM\Enums\ParticipantStatus::ASSIGNED->value,
            \Modules\MBKM\Enums\ParticipantStatus::ONGOING->value,
        ], true)) {
            throw ValidationException::withMessages([
                'participant' => ['Logbook hanya dapat diisi untuk peserta yang sedang berjalan.'],
            ]);
        }
    }
}
