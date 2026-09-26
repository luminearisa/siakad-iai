<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\CompletionStatus;
use Modules\MBKM\Enums\LearningAgreementStatus;
use Modules\MBKM\Enums\LogbookStatus;
use Modules\MBKM\Enums\ParticipantStatus;
use Modules\MBKM\Enums\RecognitionStatus;
use Modules\MBKM\Models\MbkmCompletion;
use Modules\MBKM\Models\MbkmDocument;
use Modules\MBKM\Models\MbkmParticipant;

/**
 * Completion verification.
 *
 * Which requirements apply is driven entirely by the program's policy flags and
 * requirement rows — nothing is hardcoded. When a requirement is unmet the
 * system reports exactly which one, so the participant can act on it.
 */
class MbkmCompletionService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
        protected MbkmAttendanceService $attendanceService,
        protected MbkmAssessmentService $assessmentService,
        protected MbkmLogbookService $logbookService,
    ) {}

    /**
     * Evaluate every requirement for a participant.
     *
     * @return array{
     *     status: string,
     *     requirements: array<int, array{code: string, label: string, satisfied: bool, detail: string}>,
     *     unmet: array<int, string>,
     *     is_complete: bool
     * }
     */
    public function evaluate(MbkmParticipant $participant): array
    {
        $participant->loadMissing(['program.requirements', 'program.assessmentComponents', 'learningAgreement', 'recognitions']);

        $program = $participant->program;
        $requirements = [];

        // 1. Logbook completed
        if ($program?->requires_logbook) {
            $summary = $this->logbookService->summary($participant);
            $pending = $summary['submitted'] + $summary['revision_required'] + $summary['draft'];
            $satisfied = $summary['approved'] > 0 && $pending === 0;
            $requirements[] = [
                'code' => 'logbook',
                'label' => 'Logbook Aktivitas',
                'satisfied' => $satisfied,
                'detail' => $satisfied
                    ? "{$summary['approved']} logbook disetujui."
                    : "Logbook belum tuntas ({$summary['approved']} disetujui, {$pending} belum final).",
            ];
        }

        // 2. Attendance threshold
        if ($program?->requires_attendance) {
            $summary = $this->attendanceService->summary($participant);
            $satisfied = $this->attendanceService->meetsMinimum($participant);
            $requirements[] = [
                'code' => 'attendance',
                'label' => 'Presensi',
                'satisfied' => $satisfied,
                'detail' => $satisfied
                    ? 'Presensi memenuhi batas minimum (' . ($summary['attendance_percentage'] ?? 0) . '%).'
                    : 'Presensi belum memenuhi batas minimum (' . ($summary['attendance_percentage'] ?? 0) . '%, minimum ' . ($program->min_attendance_percentage ?? 0) . '%).',
            ];
        }

        // 3. Assessment complete
        if ($program?->requires_assessment) {
            $result = $this->assessmentService->computeFinalScore($participant);
            $requirements[] = [
                'code' => 'assessment',
                'label' => 'Penilaian',
                'satisfied' => $result['is_complete'],
                'detail' => $result['is_complete']
                    ? 'Seluruh komponen penilaian telah dinilai (nilai akhir ' . $result['final_score'] . ').'
                    : 'Masih ada komponen penilaian yang belum diisi.',
            ];
        }

        // 4. Learning agreement approved
        if ($program?->requires_learning_agreement) {
            $agreement = $participant->learningAgreement;
            $status = $agreement?->status;
            $statusValue = $status instanceof LearningAgreementStatus ? $status->value : ($status ? (string) $status : null);
            $satisfied = in_array($statusValue, [
                LearningAgreementStatus::APPROVED->value,
                LearningAgreementStatus::LOCKED->value,
            ], true);

            $requirements[] = [
                'code' => 'learning_agreement',
                'label' => 'Learning Agreement',
                'satisfied' => $satisfied,
                'detail' => $satisfied
                    ? 'Learning agreement telah disetujui.'
                    : 'Learning agreement belum disetujui.',
            ];
        }

        // 5. Mandatory documents (program requirement rows + final report)
        $mandatoryDocuments = $program?->requirements
            ? $program->requirements->where('is_document', true)->where('is_mandatory', true)
            : collect();

        foreach ($mandatoryDocuments as $requirement) {
            // Documents may have been uploaded with the application or during
            // execution; both count, and neither row is moved or duplicated.
            $uploaded = $participant->hasDocumentCategory((string) $requirement->code);

            $requirements[] = [
                'code' => 'document:' . ($requirement->code ?: $requirement->id),
                'label' => 'Dokumen: ' . $requirement->name,
                'satisfied' => $uploaded,
                'detail' => $uploaded ? 'Dokumen tersedia.' : 'Dokumen wajib belum diunggah.',
            ];
        }

        // 6. Final report
        if ($program?->requires_final_report) {
            $hasReport = $participant->hasDocumentCategory('final_report');

            $requirements[] = [
                'code' => 'final_report',
                'label' => 'Laporan Akhir',
                'satisfied' => $hasReport,
                'detail' => $hasReport ? 'Laporan akhir tersedia.' : 'Laporan akhir belum diunggah.',
            ];
        }

        // 7. Recognition complete
        if ($program?->requires_recognition) {
            $recognized = $participant->recognitions->filter(function ($r) {
                $value = $r->status instanceof RecognitionStatus ? $r->status->value : (string) $r->status;

                return in_array($value, [RecognitionStatus::APPROVED->value, RecognitionStatus::LOCKED->value], true);
            })->count();

            $requirements[] = [
                'code' => 'recognition',
                'label' => 'Rekognisi SKS',
                'satisfied' => $recognized > 0,
                'detail' => $recognized > 0
                    ? "{$recognized} rekognisi telah disetujui."
                    : 'Belum ada rekognisi yang disetujui.',
            ];
        }

        $unmet = array_values(array_map(
            fn (array $r) => $r['detail'],
            array_filter($requirements, fn (array $r) => !$r['satisfied'])
        ));

        $isComplete = empty($unmet);

        return [
            'status' => $isComplete ? CompletionStatus::COMPLETED->value : CompletionStatus::REQUIREMENTS_UNMET->value,
            'requirements' => $requirements,
            'unmet' => $unmet,
            'is_complete' => $isComplete,
        ];
    }

    /**
     * Run the verification and persist the snapshot.
     */
    public function verify(MbkmParticipant $participant, User $actor, bool $force = false, ?string $notes = null): MbkmCompletion
    {
        $evaluation = $this->evaluate($participant);

        return DB::transaction(function () use ($participant, $actor, $evaluation, $force, $notes) {
            $completion = MbkmCompletion::firstOrNew(['participant_id' => $participant->id]);

            $completion->fill([
                'requirements_snapshot' => $evaluation['requirements'],
                'unmet_requirements' => $evaluation['unmet'],
                'checked_at' => now(),
                'checked_by' => $actor->id,
                'notes' => $notes,
            ]);

            if ($evaluation['is_complete'] || $force) {
                $completion->status = CompletionStatus::COMPLETED;
                $completion->verified_at = now();
                $completion->verified_by = $actor->id;
                $completion->completed_at = now();
            } else {
                $completion->status = CompletionStatus::REQUIREMENTS_UNMET;
            }

            $completion->save();

            if ($completion->status === CompletionStatus::COMPLETED) {
                $from = $participant->status instanceof ParticipantStatus
                    ? $participant->status->value
                    : (string) $participant->status;

                $participant->update([
                    'status' => ParticipantStatus::COMPLETED,
                    'completed_at' => now(),
                ]);

                $this->historyService->record(
                    entity: $participant,
                    action: 'participant.completed',
                    fromStatus: $from,
                    toStatus: ParticipantStatus::COMPLETED->value,
                    notes: $notes ?? 'Seluruh persyaratan penyelesaian terpenuhi.',
                    meta: ['forced' => $force],
                    actorId: $actor->id
                );

                $this->notificationService->notifyParticipantStudent(
                    $participant,
                    'participant.completed',
                    'Program MBKM Selesai',
                    "Selamat! Program MBKM {$participant->program?->name} Anda telah dinyatakan selesai.",
                );
            } else {
                $this->historyService->record(
                    entity: $participant,
                    action: 'participant.completion_pending',
                    notes: 'Verifikasi penyelesaian dijalankan, syarat belum lengkap.',
                    meta: ['unmet' => $evaluation['unmet']],
                    actorId: $actor->id
                );
            }

            return $completion->fresh();
        });
    }

    /**
     * Attach a completion document (certificate / completion letter) from the
     * module's single document store.
     */
    public function attachCertificate(MbkmParticipant $participant, array $fileData, User $actor): MbkmDocument
    {
        $document = $participant->documents()->create([
            'category' => $fileData['category'] ?? 'certificate',
            'title' => $fileData['title'] ?? 'Sertifikat Penyelesaian MBKM',
            'original_name' => $fileData['original_name'],
            'file_path' => $fileData['file_path'],
            'mime_type' => $fileData['mime_type'] ?? null,
            'size' => $fileData['size'] ?? 0,
            'status' => 'uploaded',
            'uploaded_by' => $actor->id,
        ]);

        $completion = MbkmCompletion::firstOrCreate(
            ['participant_id' => $participant->id],
            ['status' => CompletionStatus::PENDING]
        );

        $completion->update(['certificate_document_id' => $document->id]);

        $this->historyService->record(
            entity: $participant,
            action: 'participant.certificate_attached',
            notes: 'Dokumen penyelesaian diunggah.',
            meta: ['document_id' => $document->id],
            actorId: $actor->id
        );

        return $document;
    }

    /**
     * Withdrawal / cancellation / termination handling.
     */
    public function decideWithdrawal(
        \Modules\MBKM\Models\MbkmWithdrawalRequest $request,
        User $decider,
        string $decision,
        ?string $notes = null
    ): \Modules\MBKM\Models\MbkmWithdrawalRequest {
        $allowed = ['approved', 'rejected'];

        if (!in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages([
                'decision' => ['Keputusan tidak valid.'],
            ]);
        }

        return DB::transaction(function () use ($request, $decider, $decision, $notes) {
            $request->loadMissing('participant');

            $request->update([
                'status' => $decision,
                'decided_at' => now(),
                'decided_by' => $decider->id,
                'decision_notes' => $notes,
            ]);

            if ($decision === 'approved') {
                $participant = $request->participant;
                $type = $request->type instanceof \Modules\MBKM\Enums\WithdrawalType
                    ? $request->type->value
                    : (string) $request->type;

                $newStatus = $type === \Modules\MBKM\Enums\WithdrawalType::TERMINATION->value
                    ? ParticipantStatus::TERMINATED
                    : ParticipantStatus::WITHDRAWN;

                $from = $participant->status instanceof ParticipantStatus
                    ? $participant->status->value
                    : (string) $participant->status;

                $participant->update([
                    'status' => $newStatus,
                    'terminated_at' => now(),
                    'termination_reason' => $request->reason,
                ]);

                $this->historyService->record(
                    entity: $participant,
                    action: 'participant.' . $type,
                    fromStatus: $from,
                    toStatus: $newStatus->value,
                    notes: $request->reason,
                    actorId: $decider->id
                );

                $this->notificationService->notifyParticipantStudent(
                    $participant,
                    'participant.' . $type,
                    'Status MBKM Diperbarui',
                    'Permohonan ' . $type . ' Anda telah disetujui: ' . $request->reason,
                );
            }

            return $request->fresh();
        });
    }

    /**
     * Extension decision: updates the participant end date on approval.
     */
    public function decideExtension(
        \Modules\MBKM\Models\MbkmExtensionRequest $request,
        User $decider,
        string $decision,
        ?string $notes = null
    ): \Modules\MBKM\Models\MbkmExtensionRequest {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Keputusan tidak valid.'],
            ]);
        }

        return DB::transaction(function () use ($request, $decider, $decision, $notes) {
            $request->loadMissing('participant');

            $request->update([
                'status' => $decision,
                'decided_at' => now(),
                'decided_by' => $decider->id,
                'decision_notes' => $notes,
            ]);

            if ($decision === 'approved') {
                $participant = $request->participant;

                $participant->update([
                    'end_date' => $request->new_end_date,
                ]);

                $this->historyService->record(
                    entity: $participant,
                    action: 'participant.extended',
                    notes: 'Perpanjangan disetujui: ' . $request->reason,
                    meta: [
                        'old_end_date' => optional($request->old_end_date)->toDateString(),
                        'new_end_date' => optional($request->new_end_date)->toDateString(),
                    ],
                    actorId: $decider->id
                );

                $this->notificationService->notifyParticipantStudent(
                    $participant,
                    'participant.extended',
                    'Perpanjangan MBKM Disetujui',
                    'Tanggal selesai program Anda diperpanjang hingga ' . optional($request->new_end_date)->format('d/m/Y') . '.',
                );
            }

            return $request->fresh();
        });
    }
}
