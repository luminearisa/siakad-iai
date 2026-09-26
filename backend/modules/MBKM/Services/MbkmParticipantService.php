<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Assessment\Services\GradeCalculationService;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\ApplicationStatus;
use Modules\MBKM\Enums\CompletionStatus;
use Modules\MBKM\Enums\ParticipantStatus;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmCompletion;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmProgram;

/**
 * Participant assignment and lifecycle.
 *
 * Quota is enforced inside a database transaction with a row lock on the program
 * so two concurrent approvals can never exceed the program quota.
 */
class MbkmParticipantService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
        protected MbkmAssessmentService $assessmentService,
        protected GradeCalculationService $gradeCalculationService,
    ) {}

    /**
     * Promote a selected application into an MBKM participant.
     *
     * @throws ValidationException
     */
    public function assignFromApplication(MbkmApplication $application, User $assigner): MbkmParticipant
    {
        return DB::transaction(function () use ($application, $assigner) {
            // Lock the program row: serialises concurrent quota checks.
            /** @var MbkmProgram $program */
            $program = MbkmProgram::whereKey($application->program_id)->lockForUpdate()->firstOrFail();

            $application->loadMissing('student');
            $student = $application->student;

            if (!$student) {
                throw ValidationException::withMessages([
                    'student' => ['Mahasiswa pendaftar tidak ditemukan.'],
                ]);
            }

            $statusValue = $application->status instanceof ApplicationStatus
                ? $application->status->value
                : (string) $application->status;

            if ($statusValue !== ApplicationStatus::SELECTED->value) {
                throw ValidationException::withMessages([
                    'status' => ['Hanya pendaftar berstatus "selected" yang dapat ditetapkan sebagai peserta.'],
                ]);
            }

            if (MbkmParticipant::where('program_id', $program->id)->where('student_id', $student->id)->exists()) {
                throw ValidationException::withMessages([
                    'participant' => ['Mahasiswa ini sudah terdaftar sebagai peserta program tersebut.'],
                ]);
            }

            $used = $program->usedQuota();

            if ($program->quota !== null && ($used + 1) > $program->quota) {
                throw ValidationException::withMessages([
                    'quota' => ["Kuota program sudah penuh ({$used}/{$program->quota})."],
                ]);
            }

            $participant = MbkmParticipant::create([
                'participant_number' => $this->generateParticipantNumber($program),
                'program_id' => $program->id,
                'application_id' => $application->id,
                'student_id' => $student->id,
                'start_date' => $program->start_date,
                'end_date' => $program->end_date,
                'original_end_date' => $program->end_date,
                'status' => ParticipantStatus::ASSIGNED,
                'assigned_by' => $assigner->id,
            ]);

            // Seed the completion checklist so it can be tracked from day one.
            MbkmCompletion::firstOrCreate(
                ['participant_id' => $participant->id],
                ['status' => CompletionStatus::PENDING]
            );

            $this->historyService->record(
                entity: $participant,
                action: 'participant.assigned',
                toStatus: ParticipantStatus::ASSIGNED->value,
                notes: 'Peserta MBKM ditetapkan.',
                meta: ['application_id' => $application->id, 'quota_used' => $used + 1],
                actorId: $assigner->id
            );

            $this->notificationService->notifyParticipantStudent(
                $participant,
                'participant.assigned',
                'Anda Ditetapkan sebagai Peserta MBKM',
                "Anda resmi menjadi peserta program {$program->name} dengan nomor {$participant->participant_number}.",
                ['program_id' => $program->id]
            );

            return $participant->load(['program', 'student']);
        });
    }

    /**
     * Move an assigned participant into the execution stage.
     */
    public function start(MbkmParticipant $participant, ?User $actor = null): MbkmParticipant
    {
        $from = $participant->status instanceof ParticipantStatus
            ? $participant->status->value
            : (string) $participant->status;

        if ($from !== ParticipantStatus::ASSIGNED->value) {
            throw ValidationException::withMessages([
                'status' => ['Hanya peserta berstatus "assigned" yang dapat memulai pelaksanaan.'],
            ]);
        }

        $participant->update(['status' => ParticipantStatus::ONGOING]);

        $this->historyService->record(
            entity: $participant,
            action: 'participant.started',
            fromStatus: $from,
            toStatus: ParticipantStatus::ONGOING->value,
            notes: 'Pelaksanaan MBKM dimulai.',
            actorId: $actor?->id
        );

        $this->notificationService->notifyParticipantStudent(
            $participant,
            'participant.started',
            'Program MBKM Dimulai',
            "Program {$participant->program?->name} resmi dimulai. Selamat melaksanakan!",
        );

        return $participant->fresh(['program', 'student']);
    }

    /**
     * Administrative update of a participant: dates, notes, and the single
     * status change that has no dedicated workflow endpoint (`failed`).
     *
     * Status is never trusted blindly here. Writing the column straight from a
     * generic update let a caller jump straight to `completed` (skipping
     * placement, learning agreement, logbook, assessment and completion
     * verification) or resurrect a terminal participant — and because it
     * bypassed the history service, the change left no trace in
     * `mbkm_status_histories`. Both are closed below:
     *
     *  - the transition must exist in ParticipantStatus::transitions();
     *  - workflow-only targets (completed / withdrawn / terminated) are refused
     *    with a pointer to the endpoint that owns them;
     *  - every accepted change is written to the status history.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateAdministrative(MbkmParticipant $participant, array $data, User $actor): MbkmParticipant
    {
        return DB::transaction(function () use ($participant, $data, $actor) {
            $payload = [];

            foreach (['start_date', 'end_date', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $data[$field];
                }
            }

            $change = null;

            if (!empty($data['status'])) {
                $from = $participant->status instanceof ParticipantStatus
                    ? $participant->status
                    : ParticipantStatus::from($participant->status);

                $to = ParticipantStatus::from($data['status']);

                if ($from !== $to) {
                    if (in_array($to->value, ParticipantStatus::workflowOnly(), true)) {
                        throw ValidationException::withMessages([
                            'status' => [
                                'Status "' . $to->label() . '" hanya dapat dicapai melalui alur resminya '
                                . '(mulai pelaksanaan, verifikasi penyelesaian, atau keputusan permohonan '
                                . 'pengunduran diri), bukan melalui perubahan data peserta.',
                            ],
                        ]);
                    }

                    if (!$from->canTransitionTo($to)) {
                        throw ValidationException::withMessages([
                            'status' => [
                                'Perubahan status dari "' . $from->label() . '" ke "' . $to->label() . '" tidak diizinkan.',
                            ],
                        ]);
                    }

                    $payload['status'] = $to;
                    $change = ['from' => $from->value, 'to' => $to->value];
                }
            }

            if ($payload !== []) {
                $participant->update($payload);
            }

            if ($change !== null) {
                $this->historyService->record(
                    entity: $participant,
                    action: 'participant.status_changed',
                    fromStatus: $change['from'],
                    toStatus: $change['to'],
                    notes: 'Status peserta diubah melalui pembaruan data peserta.',
                    actorId: $actor->id
                );
            }

            return $participant->fresh(['program', 'student']);
        });
    }

    /**
     * Compute and persist the participant's final MBKM score.
     *
     * Assessment -> weighted score -> existing grade scale -> letter grade/point.
     */
    public function finalizeScore(MbkmParticipant $participant, User $actor, bool $force = false): MbkmParticipant
    {
        $participant->loadMissing('program');

        if ($participant->program?->requires_assessment) {
            $summary = $this->assessmentService->computeFinalScore($participant);

            // Refuse to freeze a grade while components are still unscored:
            // the final score would be silently understated and then flow into
            // the academic result / KHS through recognition.
            if (!$force && !($summary['is_complete'] ?? false)) {
                $pending = collect($summary['breakdown'] ?? [])
                    ->filter(fn (array $row) => $row['assessors'] === 0)
                    ->pluck('component_name')
                    ->all();

                throw ValidationException::withMessages([
                    'assessment' => [
                        'Penilaian belum lengkap. Komponen yang belum dinilai: '
                            . (empty($pending) ? '-' : implode(', ', $pending)) . '.',
                    ],
                ]);
            }
        } else {
            $summary = ['final_score' => 0.0, 'is_complete' => true, 'breakdown' => []];
        }

        $conversion = $this->gradeCalculationService->convertScoreToGrade((float) $summary['final_score']);

        $from = $participant->status instanceof ParticipantStatus
            ? $participant->status->value
            : (string) $participant->status;

        $participant->update([
            'final_score' => $summary['final_score'],
            'letter_grade' => $conversion['letter_grade'],
            'grade_point' => $conversion['grade_point'],
            'score_finalized_at' => now(),
            'score_finalized_by' => $actor->id,
        ]);

        $this->historyService->record(
            entity: $participant,
            action: 'participant.score_finalized',
            fromStatus: $from,
            toStatus: $from,
            notes: 'Nilai akhir MBKM difinalisasi.',
            meta: [
                'final_score' => $summary['final_score'],
                'letter_grade' => $conversion['letter_grade'],
                'grade_point' => $conversion['grade_point'],
            ],
            actorId: $actor->id
        );

        $this->notificationService->notifyParticipantStudent(
            $participant,
            'participant.score_finalized',
            'Nilai Akhir MBKM Telah Dihitung',
            'Nilai akhir MBKM Anda: ' . number_format((float) $summary['final_score'], 2) . ' (' . $conversion['letter_grade'] . ').',
        );

        return $participant->fresh();
    }

    /**
     * Human readable, unique participant number.
     */
    public function generateParticipantNumber(MbkmProgram $program): string
    {
        $year = now()->format('Y');
        $prefix = 'MBKM-P/' . strtoupper($program->code) . '/' . $year . '/';

        $lastSequence = MbkmParticipant::where('participant_number', 'like', $prefix . '%')->lockForUpdate()->count();

        do {
            $lastSequence++;
            $candidate = $prefix . str_pad((string) $lastSequence, 4, '0', STR_PAD_LEFT);
        } while (MbkmParticipant::where('participant_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Cached recognized credits total for a participant.
     */
    public function refreshRecognizedCredits(MbkmParticipant $participant): int
    {
        $total = (int) $participant->recognitions()
            ->whereIn('status', [
                \Modules\MBKM\Enums\RecognitionStatus::APPROVED->value,
                \Modules\MBKM\Enums\RecognitionStatus::LOCKED->value,
            ])
            ->sum('credits');

        $participant->update(['recognized_credits' => $total]);

        return $total;
    }
}
