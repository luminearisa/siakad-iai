<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\AssessorType;
use Modules\MBKM\Models\MbkmAssessment;
use Modules\MBKM\Models\MbkmAssessmentComponent;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Services\MbkmAssessmentService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Assessment: program component configuration (weights) and score entry by the
 * internal supervisor, field supervisor, partner, or the participant themself.
 */
class MbkmAssessmentController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmAssessmentService $service,
    ) {}

    /**
     * Components configured for a program (weight configuration).
     */
    public function components(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:mbkm_programs,id'],
        ]);

        $components = MbkmAssessmentComponent::where('program_id', $validated['program_id'])
            ->orderBy('sort_order')
            ->get();

        return $this->successResponse([
            'components' => $components,
            'total_weight' => (float) $components->sum('weight'),
        ], 'Komponen penilaian MBKM.');
    }

    /**
     * List assessments recorded for a participant, with the computed final score.
     */
    public function index(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke penilaian peserta ini.');
        }

        $participant->loadMissing(['program.assessmentComponents', 'assessments.component', 'assessments.assessorUser:id,name']);

        return $this->successResponse([
            'assessments' => $participant->assessments,
            'components' => $participant->program?->assessmentComponents ?? [],
            'final' => $this->service->computeFinalScore($participant),
            'participant_final_score' => $participant->final_score,
            'letter_grade' => $participant->letter_grade,
            'grade_point' => $participant->grade_point,
        ], 'Data penilaian MBKM berhasil dimuat.');
    }

    /**
     * Record a score for one component.
     */
    public function store(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayWriteParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak menilai peserta ini.');
        }

        $validated = $request->validate([
            'component_id' => ['required', 'integer', 'exists:mbkm_assessment_components,id'],
            'assessor_type' => ['nullable', 'string', Rule::in(AssessorType::values())],
            'assessor_name' => ['nullable', 'string', 'max:255'],
            'score' => ['required', 'numeric', 'min:0'],
            'max_score' => ['nullable', 'numeric', 'min:1'],
            'feedback' => ['nullable', 'string'],
            'recommendation' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,submitted,verified'],
        ]);

        $component = MbkmAssessmentComponent::findOrFail($validated['component_id']);

        // A participant may only record their own self-assessment; assessor types
        // like partner/field supervisor require staff or supervisor rights.
        if ($this->isMbkmStudent($request)) {
            if ($this->currentStudent($request)?->id !== $participant->student_id) {
                return $this->mbkmDeny('Anda tidak berhak menilai peserta ini.');
            }

            $validated['assessor_type'] = 'self';
        }

        $assessment = $this->service->record($participant, $component, $validated, $request->user());

        return $this->successResponse($assessment, 'Penilaian MBKM berhasil disimpan.', 201);
    }

    /**
     * Partner (external) assessment shortcut: score + feedback + recommendation.
     *
     * The external partner/field supervisor usually has no system account, so a
     * staff member or one of the participant's supervisors records the score on
     * their behalf. The participant themself is excluded (they already have the
     * self-assessment endpoint), and the acting user must be scoped to this
     * participant — never trust the participant id from the URL alone.
     */
    public function storePartnerAssessment(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak mencatat penilaian mitra untuk peserta ini.');
        }

        $validated = $request->validate([
            'component_id' => ['required', 'integer', 'exists:mbkm_assessment_components,id'],
            'assessor_name' => ['required', 'string', 'max:255'],
            'score' => ['required', 'numeric', 'min:0'],
            'max_score' => ['nullable', 'numeric', 'min:1'],
            'feedback' => ['nullable', 'string'],
            'recommendation' => ['nullable', 'string'],
        ]);

        $component = MbkmAssessmentComponent::findOrFail($validated['component_id']);
        $validated['assessor_type'] = 'partner';

        $assessment = $this->service->record($participant, $component, $validated, $request->user());

        return $this->successResponse($assessment, 'Penilaian mitra berhasil disimpan.', 201);
    }

    public function destroy(Request $request, MbkmAssessment $assessment): JsonResponse
    {
        $assessment->loadMissing('participant');
        $participant = $assessment->participant;

        if (!$participant) {
            return $this->mbkmDeny('Data peserta untuk penilaian ini tidak ditemukan.');
        }

        if ($this->isMbkmStudent($request)) {
            // Mirrors the rule enforced in store(): a participant only ever owns
            // their own `self` rows. The old check was participant-scoped only,
            // so a student could delete the *supervisor's* or the *partner's*
            // score for a component — which silently raised the weighted average
            // of the rows that remained, and with it the final grade.
            $ownsSelfAssessment = $this->currentStudent($request)?->id === $participant->student_id
                && (string) $assessment->assessor_type === AssessorType::SELF->value
                && (int) $assessment->assessor_user_id === (int) $request->user()?->id;

            if (!$ownsSelfAssessment) {
                return $this->mbkmDeny('Anda hanya dapat menghapus penilaian yang Anda catat sendiri.');
            }
        } elseif (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak menghapus penilaian ini.');
        }

        // Same freeze as recording: a finalized score must not lose the
        // components it was computed from.
        if ($participant->score_finalized_at !== null) {
            return $this->errorResponse('Nilai akhir peserta sudah difinalisasi sehingga penilaian tidak dapat dihapus.', 422);
        }

        $assessment->delete();

        return $this->successResponse(null, 'Penilaian berhasil dihapus.');
    }
}
