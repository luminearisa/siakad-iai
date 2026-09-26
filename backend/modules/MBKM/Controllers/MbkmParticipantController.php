<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MBKM\Enums\ParticipantStatus;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Services\MbkmAssessmentService;
use Modules\MBKM\Services\MbkmAttendanceService;
use Modules\MBKM\Services\MbkmCompletionService;
use Modules\MBKM\Services\MbkmHistoryService;
use Modules\MBKM\Services\MbkmLogbookService;
use Modules\MBKM\Services\MbkmParticipantService;
use Modules\MBKM\Services\MbkmPlacementService;
use Modules\MBKM\Services\MbkmRecognitionService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * MBKM participants: assignment from a selected application, execution lifecycle,
 * final score finalization, and the participant 360 view.
 */
class MbkmParticipantController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmParticipantService $participantService,
        protected MbkmPlacementService $placementService,
        protected MbkmLogbookService $logbookService,
        protected MbkmAttendanceService $attendanceService,
        protected MbkmAssessmentService $assessmentService,
        protected MbkmRecognitionService $recognitionService,
        protected MbkmCompletionService $completionService,
        protected MbkmHistoryService $historyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MbkmParticipant::query()->with([
            'program.programType',
            'program.semester',
            'student.studyProgram',
            'placement.partner',
            'placement.location',
            'supervisors.lecturer',
            'completion',
        ]);

        // Scope by `visibleStudentIds()` rather than by ad-hoc role branches:
        // the previous student/lecturer branches left *every other* role
        // unscoped, so a user holding no mbkm.* permission at all — for example
        // `admin_akademik` — fell through and listed every participant in the
        // institution. `null` means unrestricted (module manager), `[]` means
        // "sees nothing", and an empty `whereIn` matches no rows.
        $visible = $this->visibleStudentIds($request);

        if ($visible !== null) {
            $query->whereIn('student_id', $visible);
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->query('program_id'));
        }

        if ($request->filled('study_program_id')) {
            $query->whereHas('student', fn ($q) => $q->where('study_program_id', $request->query('study_program_id')));
        }

        if ($request->filled('partner_id')) {
            $query->whereHas('placement', fn ($q) => $q->where('partner_id', $request->query('partner_id')));
        }

        if ($request->filled('lecturer_id')) {
            $query->whereHas('supervisors', fn ($q) => $q->where('lecturer_id', $request->query('lecturer_id')));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['participant_number', 'student.full_name', 'student.student_number'],
            filterableColumns: ['status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Data peserta MBKM berhasil dimuat.');
    }

    /**
     * Promote a selected application into a participant (quota-locked).
     */
    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'application_id' => ['required', 'integer', 'exists:mbkm_applications,id'],
        ]);

        $application = MbkmApplication::findOrFail($validated['application_id']);

        $participant = $this->participantService->assignFromApplication($application, $request->user());

        return $this->successResponse($participant, 'Peserta MBKM berhasil ditetapkan.', 201);
    }

    public function show(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke data peserta MBKM ini.');
        }

        $participant->load([
            'program.programType',
            'program.semester',
            'program.assessmentComponents',
            'program.requirements',
            'application',
            'student.studyProgram.faculty',
            'student.academicAdvisor.lecturer',
            'placement.partner',
            'placement.location',
            'supervisors.lecturer',
            'learningAgreement.items.course',
            'activityPlans',
            'completion.certificateDocument',
        ]);

        return $this->successResponse([
            'participant' => $participant,
            'attendance' => $this->attendanceService->summary($participant),
            'logbook' => $this->logbookService->summary($participant),
            'assessment' => $this->assessmentService->computeFinalScore($participant),
            'recognition' => $this->recognitionService->summary($participant),
            'completion' => $this->completionService->evaluate($participant),
            'history' => $this->historyService->forEntity($participant),
        ], 'Detail peserta MBKM.');
    }

    /**
     * Start the execution stage (assigned -> ongoing).
     */
    public function start(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak memulai pelaksanaan peserta ini.');
        }

        $started = $this->participantService->start($participant, $request->user());

        return $this->successResponse($started, 'Pelaksanaan MBKM dimulai.');
    }

    /**
     * Compute and persist the final MBKM score.
     *
     * Refuses while assessment components are still unscored unless `force` is
     * set — a frozen grade flows into the academic result / KHS, so it must not
     * be written from a partial assessment.
     */
    public function finalizeScore(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak memfinalisasi nilai peserta ini.');
        }

        $validated = $request->validate([
            'force' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $finalized = $this->participantService->finalizeScore(
            $participant,
            $request->user(),
            (bool) ($validated['force'] ?? false)
        );

        return $this->successResponse($finalized, 'Nilai akhir MBKM berhasil difinalisasi.');
    }

    public function update(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak mengubah data peserta ini.');
        }

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:' . implode(',', ParticipantStatus::values())],
        ]);

        // The status transition is validated (and audited) in the service, not
        // written straight to the column: see MbkmParticipantService.
        $updated = $this->participantService->updateAdministrative($participant, $validated, $request->user());

        return $this->successResponse($updated, 'Data peserta MBKM berhasil diperbarui.');
    }

    // -----------------------------------------------------------------
    // Placement + supervisors
    // -----------------------------------------------------------------

    public function upsertPlacement(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak mengatur penempatan peserta ini.');
        }

        $validated = $request->validate([
            'partner_id' => ['nullable', 'integer', 'exists:mbkm_partners,id'],
            'location_id' => ['nullable', 'integer', 'exists:mbkm_program_locations,id'],
            'division' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'batch' => ['nullable', 'string', 'max:120'],
            'field_supervisor_name' => ['nullable', 'string', 'max:255'],
            'field_supervisor_position' => ['nullable', 'string', 'max:255'],
            'field_supervisor_email' => ['nullable', 'email', 'max:255'],
            'field_supervisor_phone' => ['nullable', 'string', 'max:40'],
            'field_supervisor_organization' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:active,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $placement = $this->placementService->upsertPlacement($participant, $validated, $request->user());

        return $this->successResponse($placement, 'Penempatan peserta berhasil disimpan.');
    }

    public function storeSupervisor(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak menetapkan pembimbing peserta ini.');
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:internal,co_supervisor,field'],
            'lecturer_id' => ['nullable', 'integer', 'exists:lecturers,id'],
            'external_name' => ['nullable', 'string', 'max:255'],
            'external_position' => ['nullable', 'string', 'max:255'],
            'external_email' => ['nullable', 'email', 'max:255'],
            'external_phone' => ['nullable', 'string', 'max:40'],
            'external_organization' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $supervisor = $this->placementService->assignSupervisor($participant, $validated, $request->user());

        return $this->successResponse($supervisor, 'Pembimbing berhasil ditetapkan.', 201);
    }

    public function destroySupervisor(Request $request, MbkmParticipant $participant, int $supervisor): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak menghapus pembimbing peserta ini.');
        }

        $model = \Modules\MBKM\Models\MbkmSupervisor::findOrFail($supervisor);

        $this->placementService->removeSupervisor($participant, $model, $request->user());

        return $this->successResponse(null, 'Penugasan pembimbing berhasil dihapus.');
    }

    public function history(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke data peserta MBKM ini.');
        }

        return $this->successResponse($this->historyService->forEntity($participant), 'Riwayat peserta MBKM.');
    }
}
