<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\ApplicationStatus;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Services\MbkmApplicationService;
use Modules\MBKM\Services\MbkmHistoryService;
use Modules\MBKM\Services\MbkmSelectionService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * MBKM applications: student submission, staff verification, selection decision,
 * and student withdrawal. Every endpoint resolves ownership server-side.
 */
class MbkmApplicationController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmApplicationService $applicationService,
        protected MbkmSelectionService $selectionService,
        protected MbkmHistoryService $historyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MbkmApplication::query()->with([
            'program.programType',
            'program.semester',
            'student.studyProgram',
            'student.user:id,name,email',
            'verifier:id,name',
            'decider:id,name',
            'participant',
        ])->withCount('documents');

        // Shared scope helper: role branches alone left a study-program-scoped
        // manager (kaprodi) — who is also a `dosen`, so `isMbkmLecturerOnly()`
        // is false — with no scope at all.
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

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['registration_number', 'student.full_name', 'student.student_number'],
            filterableColumns: [],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Data pendaftaran MBKM berhasil dimuat.');
    }

    /**
     * Create (or reuse) the student's application for a program.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:mbkm_programs,id'],
            'motivation_statement' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Staff may register on behalf of a student.
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ]);

        $program = MbkmProgram::findOrFail($validated['program_id']);

        if ($this->isMbkmStudent($request)) {
            $student = $this->requireStudent($request);
        } else {
            if (!$this->isMbkmManager($request)) {
                return $this->mbkmDeny('Hanya mahasiswa atau pengelola MBKM yang dapat membuat pendaftaran.');
            }

            if (empty($validated['student_id'])) {
                return $this->errorResponse('student_id wajib diisi saat mendaftarkan atas nama mahasiswa.', 422);
            }

            $student = \Modules\Student\Models\Student::findOrFail($validated['student_id']);
        }

        $application = $this->applicationService->create($program, $student, $validated, $request->user());

        return $this->successResponse(
            $application->load(['program', 'student']),
            'Pendaftaran MBKM berhasil dibuat.',
            201
        );
    }

    public function show(Request $request, MbkmApplication $application): JsonResponse
    {
        if (!$this->mayAccessApplication($request, $application)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke pendaftaran MBKM ini.');
        }

        $application->load([
            'program.programType',
            'program.requirements',
            'program.locations',
            'student.studyProgram.faculty',
            'verifier:id,name',
            'decider:id,name',
            'documents.uploader:id,name',
            'participant',
        ]);

        $selection = $this->selectionService->computeWeightedScore($application);

        return $this->successResponse([
            'application' => $application,
            'selection' => $selection,
            'history' => $this->historyService->forEntity($application),
        ], 'Detail pendaftaran MBKM.');
    }

    public function update(Request $request, MbkmApplication $application): JsonResponse
    {
        if (!$this->mayWriteApplication($request, $application)) {
            return $this->mbkmDeny('Anda tidak berhak mengubah pendaftaran MBKM ini.');
        }

        $statusValue = $application->status instanceof ApplicationStatus
            ? $application->status->value
            : (string) $application->status;

        if (!in_array($statusValue, [ApplicationStatus::DRAFT->value, ApplicationStatus::REVISION_REQUIRED->value], true)) {
            return $this->errorResponse('Pendaftaran dengan status ' . $statusValue . ' tidak dapat diubah.', 422);
        }

        $validated = $request->validate([
            'motivation_statement' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update($validated);

        return $this->successResponse($application->fresh(), 'Pendaftaran MBKM berhasil diperbarui.');
    }

    public function submit(Request $request, MbkmApplication $application): JsonResponse
    {
        if (!$this->mayWriteApplication($request, $application)) {
            return $this->mbkmDeny('Anda tidak berhak mengajukan pendaftaran MBKM ini.');
        }

        $submitted = $this->applicationService->submit($application, $request->user());

        return $this->successResponse($submitted, 'Pendaftaran MBKM berhasil diajukan.');
    }

    /**
     * Staff verification (administrative / academic / document check).
     */
    public function verify(Request $request, MbkmApplication $application): JsonResponse
    {
        if (!$this->mayManageApplication($request, $application)) {
            return $this->mbkmDeny('Anda tidak berhak memverifikasi pendaftaran MBKM ini.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in([
                ApplicationStatus::VERIFIED->value,
                ApplicationStatus::REJECTED->value,
                ApplicationStatus::REVISION_REQUIRED->value,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $verified = $this->applicationService->verify(
            application: $application,
            verifier: $request->user(),
            decision: $validated['decision'],
            notes: $validated['notes'] ?? null
        );

        return $this->successResponse($verified, 'Verifikasi pendaftaran berhasil disimpan.');
    }

    /**
     * Selection decision.
     */
    public function decide(Request $request, MbkmApplication $application): JsonResponse
    {
        if (!$this->mayManageApplication($request, $application)) {
            return $this->mbkmDeny('Anda tidak berhak memutuskan seleksi pendaftaran MBKM ini.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in([
                ApplicationStatus::SELECTED->value,
                ApplicationStatus::NOT_SELECTED->value,
                ApplicationStatus::REJECTED->value,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $decided = $this->selectionService->decide(
            application: $application,
            decider: $request->user(),
            decision: $validated['decision'],
            notes: $validated['notes'] ?? null
        );

        return $this->successResponse($decided, 'Keputusan seleksi berhasil disimpan.');
    }

    /**
     * Record a reviewer score for a selection criterion.
     */
    public function score(Request $request, MbkmApplication $application): JsonResponse
    {
        if (!$this->mayManageApplication($request, $application)) {
            return $this->mbkmDeny('Anda tidak berhak menilai seleksi pendaftaran MBKM ini.');
        }

        $validated = $request->validate([
            'criteria_id' => ['required', 'integer', 'exists:mbkm_selection_criteria,id'],
            'score' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $criteria = \Modules\MBKM\Models\MbkmSelectionCriteria::findOrFail($validated['criteria_id']);

        $score = $this->selectionService->score(
            application: $application,
            criteria: $criteria,
            reviewer: $request->user(),
            score: (float) $validated['score'],
            notes: $validated['notes'] ?? null
        );

        $application->update(['selection_score' => $this->selectionService->computeWeightedScore($application)['score']]);

        return $this->successResponse($score, 'Nilai seleksi berhasil disimpan.');
    }

    /**
     * Student withdraws their own application.
     */
    public function withdraw(Request $request, MbkmApplication $application): JsonResponse
    {
        if (!$this->mayWriteApplication($request, $application)) {
            return $this->mbkmDeny('Anda tidak berhak membatalkan pendaftaran MBKM ini.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $withdrawn = $this->applicationService->withdraw($application, $validated['reason'], $request->user());

        return $this->successResponse($withdrawn, 'Pendaftaran MBKM berhasil dibatalkan.');
    }

    public function history(Request $request, MbkmApplication $application): JsonResponse
    {
        if (!$this->mayAccessApplication($request, $application)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke pendaftaran MBKM ini.');
        }

        return $this->successResponse($this->historyService->forEntity($application), 'Riwayat pendaftaran MBKM.');
    }

    protected function mayAccessApplication(Request $request, MbkmApplication $application): bool
    {
        // Deliberately the *same* predicate that scopes index(): a viewer who
        // cannot see the student's rows in the list must not be able to open
        // one by id, and — the case this used to break — a study-program
        // scoped manager (kaprodi) whose list already shows their program's
        // applications must be able to open them. The old version handled only
        // the owning student and a plain supervising lecturer and then fell
        // through to `false`, so a kaprodi saw a list of rows where every
        // detail view answered 403.
        return $this->maySeeStudent($request, $application->student_id);
    }

    protected function mayWriteApplication(Request $request, MbkmApplication $application): bool
    {
        if ($this->isMbkmManager($request)) {
            return true;
        }

        return $this->isMbkmStudent($request) && $this->currentStudent($request)?->id === $application->student_id;
    }

    /**
     * Staff-side write access to an application (verification, selection
     * decision, reviewer scoring).
     *
     * The route middleware only proves the actor holds a module-wide permission
     * such as `mbkm.applications.decide`; it says nothing about *which*
     * applications that actor may act on. Without this check a kaprodi granted
     * those permissions could verify, score and decide applications belonging
     * to any other study program — the same rule mayManageParticipant() already
     * enforces for participants.
     */
    protected function mayManageApplication(Request $request, MbkmApplication $application): bool
    {
        if ($this->isMbkmManager($request)) {
            return true;
        }

        return $this->managesStudentStudyProgram($request, $application->student_id);
    }
}
