<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\MBKM\Models\MbkmAssessment;
use Modules\MBKM\Models\MbkmAssessmentComponent;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmProgram;

/**
 * Assessment stage.
 *
 * Components and weights are program configuration (mbkm_assessment_components);
 * the final score is the weighted sum, normalised to the existing 0-100 scale so
 * it can be fed straight into the repository's grade scale.
 */
class MbkmAssessmentService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
    ) {}

    /**
     * Replace the assessment component configuration of a program.
     *
     * @param  array<int, array<string, mixed>>  $components
     */
    public function syncComponents(MbkmProgram $program, array $components): void
    {
        DB::transaction(function () use ($program, $components) {
            $program->assessmentComponents()->delete();

            foreach (array_values($components) as $index => $row) {
                MbkmAssessmentComponent::create([
                    'program_id' => $program->id,
                    'code' => $row['code'] ?? ('C' . ($index + 1)),
                    'name' => $row['name'],
                    'type' => $row['type'] ?? 'other',
                    'weight' => $row['weight'] ?? 0,
                    'max_score' => $row['max_score'] ?? 100,
                    'assessor_type' => $row['assessor_type'] ?? 'internal_supervisor',
                    'description' => $row['description'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'is_active' => $row['is_active'] ?? true,
                ]);
            }
        });
    }

    /**
     * Record (or update) an assessment score.
     */
    public function record(
        MbkmParticipant $participant,
        MbkmAssessmentComponent $component,
        array $data,
        User $assessor
    ): MbkmAssessment {
        if ((int) $component->program_id !== (int) $participant->program_id) {
            throw ValidationException::withMessages([
                'component_id' => ['Komponen penilaian tidak berasal dari program peserta ini.'],
            ]);
        }

        // A finalized score is frozen: the weighted result has already been
        // computed, converted to a letter grade and pushed towards the academic
        // result. Adding or overwriting a component afterwards would silently
        // desynchronise `final_score` from the components it claims to summarise.
        if ($participant->score_finalized_at !== null) {
            throw ValidationException::withMessages([
                'assessment' => ['Nilai akhir peserta sudah difinalisasi sehingga komponen penilaian tidak dapat ditambah atau diubah.'],
            ]);
        }

        $maxScore = (float) ($data['max_score'] ?? $component->max_score ?? 100);
        $score = (float) $data['score'];

        if ($score < 0 || $score > $maxScore) {
            throw ValidationException::withMessages([
                'score' => ["Nilai harus berada di antara 0 dan {$maxScore}."],
            ]);
        }

        $assessorType = $data['assessor_type'] ?? $component->assessor_type ?? 'internal_supervisor';
        $assessorLecturerId = $data['assessor_lecturer_id'] ?? $assessor->lecturer?->id;

        $assessment = MbkmAssessment::updateOrCreate(
            [
                'participant_id' => $participant->id,
                'component_id' => $component->id,
                'assessor_type' => $assessorType,
                'assessor_user_id' => $assessor->id,
            ],
            [
                'assessor_lecturer_id' => $assessorLecturerId,
                'assessor_name' => $data['assessor_name'] ?? $assessor->name,
                'score' => $score,
                'max_score' => $maxScore,
                'feedback' => $data['feedback'] ?? null,
                'recommendation' => $data['recommendation'] ?? null,
                'status' => $data['status'] ?? 'submitted',
                'assessed_at' => now(),
            ]
        );

        $this->historyService->record(
            entity: $participant,
            action: 'assessment.recorded',
            notes: 'Penilaian komponen ' . $component->name . ' sebesar ' . $score . '.',
            meta: [
                'assessment_id' => $assessment->id,
                'component_id' => $component->id,
                'component_name' => $component->name,
                'score' => $score,
                'max_score' => $maxScore,
                'assessor_type' => $assessorType,
            ],
            actorId: $assessor->id
        );

        return $assessment;
    }

    /**
     * Weighted final score for a participant.
     *
     * @return array{final_score: float, breakdown: array<int, array<string, mixed>>, is_complete: bool, total_weight: float}
     */
    public function computeFinalScore(MbkmParticipant $participant): array
    {
        $participant->loadMissing(['program.assessmentComponents', 'assessments.component']);

        $components = ($participant->program?->assessmentComponents ?? collect())
            ->where('is_active', true)
            ->values();

        if ($components->isEmpty()) {
            return ['final_score' => 0.0, 'breakdown' => [], 'is_complete' => false, 'total_weight' => 0.0];
        }

        $assessments = $participant->assessments->groupBy('component_id');
        $total = 0.0;
        $totalWeight = 0.0;
        $weightedWeight = 0.0;
        $breakdown = [];
        $isComplete = true;

        foreach ($components as $component) {
            $weight = (float) $component->weight;
            $maxScore = (float) ($component->max_score ?: 100);
            $rows = $assessments->get($component->id, collect());

            $totalWeight += $weight;

            if ($rows->isEmpty()) {
                $isComplete = false;
                $breakdown[] = [
                    'component_id' => $component->id,
                    'component_code' => $component->code,
                    'component_name' => $component->name,
                    'type' => $component->type,
                    'assessor_type' => $component->assessor_type,
                    'weight' => $weight,
                    'average_score' => null,
                    'normalized_score' => null,
                    'weighted_score' => 0.0,
                    'assessors' => 0,
                ];
                continue;
            }

            // Normalise each assessor to 0-100 using that assessment's own max score.
            $normalizedRows = $rows->map(function (MbkmAssessment $a) {
                $rowMax = (float) ($a->max_score ?: 100);

                return $rowMax > 0 ? ((float) $a->score / $rowMax) * 100 : 0.0;
            });

            $average = (float) $normalizedRows->avg();
            $weighted = $average * ($weight / 100);

            $total += $weighted;
            $weightedWeight += $weight;

            $breakdown[] = [
                'component_id' => $component->id,
                'component_code' => $component->code,
                'component_name' => $component->name,
                'type' => $component->type,
                'assessor_type' => $component->assessor_type,
                'weight' => $weight,
                'average_score' => round($average, 2),
                'normalized_score' => round($average, 2),
                'weighted_score' => round($weighted, 2),
                'assessors' => $rows->count(),
                'feedbacks' => $rows->pluck('feedback')->filter()->values()->all(),
            ];
        }

        return [
            'final_score' => round($total, 2),
            'breakdown' => $breakdown,
            'is_complete' => $isComplete && abs($weightedWeight - $totalWeight) < 0.01,
            'total_weight' => round($totalWeight, 2),
        ];
    }

    /**
     * Notify supervisors that assessments are pending for a participant.
     */
    public function notifyPendingAssessment(MbkmParticipant $participant): void
    {
        $this->notificationService->notifyParticipantSupervisors(
            $participant,
            'assessment.pending',
            'Penilaian MBKM Menunggu',
            'Penilaian MBKM untuk ' . ($participant->student?->full_name ?? 'peserta') . ' menunggu diisi.',
        );
    }
}
