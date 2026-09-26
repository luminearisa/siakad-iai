<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\ApplicationStatus;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Models\MbkmSelectionCriteria;
use Modules\MBKM\Models\MbkmSelectionScore;

/**
 * Selection stage: configurable criteria + weights, per-reviewer scoring, and the
 * final decision. Ranking is *not* forced: the institution decides, the system
 * only surfaces the computed weighted score as a decision aid.
 */
class MbkmSelectionService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
    ) {}

    /**
     * Replace the criteria set of a program.
     *
     * @param  array<int, array<string, mixed>>  $criteria
     * @return Collection<int, MbkmSelectionCriteria>
     */
    public function syncCriteria(MbkmProgram $program, array $criteria): Collection
    {
        return DB::transaction(function () use ($program, $criteria) {
            $program->selectionCriteria()->delete();

            return collect($criteria)->values()->map(function (array $row, int $index) use ($program) {
                return MbkmSelectionCriteria::create([
                    'program_id' => $program->id,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'weight' => $row['weight'] ?? 0,
                    'max_score' => $row['max_score'] ?? 100,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'is_active' => $row['is_active'] ?? true,
                ]);
            });
        });
    }

    /**
     * Record a reviewer score for one criterion of one application.
     */
    public function score(
        MbkmApplication $application,
        MbkmSelectionCriteria $criteria,
        User $reviewer,
        float $score,
        ?string $notes = null
    ): MbkmSelectionScore {
        if ((int) $criteria->program_id !== (int) $application->program_id) {
            throw ValidationException::withMessages([
                'criteria_id' => ['Kriteria seleksi tidak berasal dari program pendaftaran ini.'],
            ]);
        }

        if ($score < 0 || $score > (float) $criteria->max_score) {
            throw ValidationException::withMessages([
                'score' => ["Nilai harus berada di antara 0 dan {$criteria->max_score}."],
            ]);
        }

        return MbkmSelectionScore::updateOrCreate(
            [
                'application_id' => $application->id,
                'criteria_id' => $criteria->id,
                'reviewer_id' => $reviewer->id,
            ],
            [
                'score' => $score,
                'notes' => $notes,
                'scored_at' => now(),
            ]
        );
    }

    /**
     * Weighted selection score of an application.
     *
     * For each criterion the reviewer scores are averaged (normalised to 0-100),
     * then multiplied by the criterion weight. Weights are program data.
     *
     * @return array{score: float, breakdown: array<int, array<string, mixed>>, is_complete: bool}
     */
    public function computeWeightedScore(MbkmApplication $application): array
    {
        $application->loadMissing(['program.selectionCriteria', 'scores']);

        $criteria = $application->program?->selectionCriteria ?? collect();

        if ($criteria->isEmpty()) {
            return ['score' => 0.0, 'breakdown' => [], 'is_complete' => false];
        }

        $scores = $application->scores->groupBy('criteria_id');
        $total = 0.0;
        $totalWeightScored = 0.0;
        $breakdown = [];
        $isComplete = true;

        foreach ($criteria as $criterion) {
            $weight = (float) $criterion->weight;
            $maxScore = (float) ($criterion->max_score ?: 100);
            $rows = $scores->get($criterion->id, collect());

            if ($rows->isEmpty()) {
                $isComplete = false;
                $breakdown[] = [
                    'criteria_id' => $criterion->id,
                    'criteria_name' => $criterion->name,
                    'weight' => $weight,
                    'average_score' => null,
                    'normalized_score' => null,
                    'weighted_score' => 0.0,
                    'reviewers' => 0,
                ];
                continue;
            }

            $average = (float) $rows->avg('score');
            $normalized = $maxScore > 0 ? ($average / $maxScore) * 100 : 0.0;
            $weighted = $normalized * ($weight / 100);

            $total += $weighted;
            $totalWeightScored += $weight;

            $breakdown[] = [
                'criteria_id' => $criterion->id,
                'criteria_name' => $criterion->name,
                'weight' => $weight,
                'average_score' => round($average, 2),
                'normalized_score' => round($normalized, 2),
                'weighted_score' => round($weighted, 2),
                'reviewers' => $rows->count(),
            ];
        }

        $totalWeight = (float) $criteria->sum('weight');

        return [
            'score' => round($total, 2),
            'breakdown' => $breakdown,
            'is_complete' => $isComplete && $totalWeightScored >= $totalWeight - 0.01,
        ];
    }

    /**
     * Record the selection decision for an application.
     *
     * @param  string  $decision  ApplicationStatus value: selected | not_selected | rejected
     */
    public function decide(MbkmApplication $application, User $decider, string $decision, ?string $notes = null): MbkmApplication
    {
        $allowed = [
            ApplicationStatus::SELECTED->value,
            ApplicationStatus::NOT_SELECTED->value,
            ApplicationStatus::REJECTED->value,
        ];

        if (!in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages([
                'decision' => ['Keputusan seleksi tidak valid.'],
            ]);
        }

        $application->loadMissing(['program', 'student']);

        if ($application->program && $application->program->status !== \Modules\MBKM\Enums\ProgramStatus::SELECTION
            && $application->program->status !== \Modules\MBKM\Enums\ProgramStatus::REGISTRATION_CLOSED) {
            throw ValidationException::withMessages([
                'program' => ['Program belum berada pada tahap seleksi.'],
            ]);
        }

        $computed = $this->computeWeightedScore($application);
        $from = $application->status instanceof ApplicationStatus
            ? $application->status->value
            : (string) $application->status;

        $application->update([
            'status' => $decision,
            'selection_score' => $computed['score'],
            'decided_at' => now(),
            'decided_by' => $decider->id,
            'decision_notes' => $notes,
        ]);

        $this->historyService->record(
            entity: $application,
            action: 'application.decision',
            fromStatus: $from,
            toStatus: $decision,
            notes: $notes,
            meta: ['selection_score' => $computed['score'], 'breakdown' => $computed['breakdown']],
            actorId: $decider->id
        );

        if ($decision === ApplicationStatus::SELECTED->value) {
            $this->notificationService->notifyStudent(
                $application->student,
                'application.selected',
                'Selamat! Anda Lolos Seleksi MBKM',
                "Anda lolos seleksi program {$application->program?->name}. Menunggu penetapan sebagai peserta.",
                ['application_id' => $application->id]
            );
        } else {
            $this->notificationService->notifyStudent(
                $application->student,
                'application.not_selected',
                'Hasil Seleksi MBKM',
                "Anda belum lolos seleksi program {$application->program?->name}.",
                ['application_id' => $application->id]
            );
        }

        return $application->fresh(['program', 'student']);
    }

    /**
     * Recompute and persist ranks within a program (decision aid only).
     */
    public function refreshRanks(MbkmProgram $program): void
    {
        $applications = MbkmApplication::where('program_id', $program->id)
            ->whereIn('status', [ApplicationStatus::VERIFIED->value, ApplicationStatus::SELECTED->value, ApplicationStatus::NOT_SELECTED->value])
            ->orderByDesc('selection_score')
            ->orderBy('id')
            ->get();

        foreach ($applications as $index => $application) {
            $application->update(['selection_rank' => $index + 1]);
        }
    }
}
