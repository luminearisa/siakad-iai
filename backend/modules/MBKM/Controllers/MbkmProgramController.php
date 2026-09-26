<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\MBKM\Enums\AssessmentComponentType;
use Modules\MBKM\Enums\AssessorType;
use Modules\MBKM\Enums\ProgramStatus;
use Modules\MBKM\Models\MbkmAssessmentComponent;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Models\MbkmProgramLocation;
use Modules\MBKM\Models\MbkmProgramRequirement;
use Modules\MBKM\Services\MbkmAssessmentService;
use Modules\MBKM\Services\MbkmEligibilityService;
use Modules\MBKM\Services\MbkmHistoryService;
use Modules\MBKM\Services\MbkmSelectionService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * MBKM program master: CRUD, lifecycle transitions, and the nested configuration
 * (locations, requirements, selection criteria, assessment components).
 */
class MbkmProgramController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmEligibilityService $eligibilityService,
        protected MbkmSelectionService $selectionService,
        protected MbkmAssessmentService $assessmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MbkmProgram::query()->with([
            'programType',
            'semester.academicYear',
            'studyProgram',
            'faculty',
        ])->withCount(['applications', 'participants']);

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        if ($request->filled('program_type_id')) {
            $query->where('program_type_id', $request->query('program_type_id'));
        }

        if ($request->filled('faculty_id')) {
            $query->where('faculty_id', $request->query('faculty_id'));
        }

        if ($request->filled('study_program_id')) {
            $query->where('study_program_id', $request->query('study_program_id'));
        }

        if ($request->filled('location_mode')) {
            $query->where('location_mode', $request->query('location_mode'));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code', 'organizer_name'],
            filterableColumns: ['status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        $items = collect($paginator->items())->map(function (MbkmProgram $program) {
            $program->setAttribute('quota_used', $program->usedQuota());

            return $program;
        });

        return $this->successResponse(
            data: $items,
            message: 'Data program MBKM berhasil dimuat.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProgram($request);
        $validated['created_by'] = $request->user()?->id;
        $validated['status'] = $validated['status'] ?? ProgramStatus::DRAFT->value;

        $program = MbkmProgram::create($validated);

        $this->historyService->record(
            entity: $program,
            action: 'program.created',
            toStatus: $program->status instanceof ProgramStatus ? $program->status->value : (string) $program->status,
            notes: 'Program MBKM dibuat.',
            actorId: $request->user()?->id
        );

        return $this->successResponse($program->load(['programType', 'semester']), 'Program MBKM berhasil dibuat.', 201);
    }

    public function show(MbkmProgram $program): JsonResponse
    {
        $program->load([
            'programType',
            'faculty',
            'studyProgram',
            'semester.academicYear',
            'locations',
            'requirements',
            'cooperations.partner',
            'selectionCriteria',
            'assessmentComponents',
            'creator:id,name',
        ]);

        $program->setAttribute('quota_used', $program->usedQuota());

        return $this->successResponse($program, 'Detail program MBKM.');
    }

    public function update(Request $request, MbkmProgram $program): JsonResponse
    {
        $validated = $this->validateProgram($request, $program->id);

        $from = $program->status instanceof ProgramStatus ? $program->status->value : (string) $program->status;

        $program->update($validated);

        $this->historyService->record(
            entity: $program,
            action: 'program.updated',
            fromStatus: $from,
            toStatus: $program->status instanceof ProgramStatus ? $program->status->value : (string) $program->status,
            notes: 'Program MBKM diperbarui.',
            actorId: $request->user()?->id
        );

        return $this->successResponse($program->fresh(['programType', 'semester']), 'Program MBKM berhasil diperbarui.');
    }

    public function destroy(MbkmProgram $program): JsonResponse
    {
        if ($program->participants()->exists()) {
            return $this->errorResponse('Program yang sudah memiliki peserta tidak dapat dihapus. Gunakan pembatalan/arsip.', 422);
        }

        $program->delete();

        return $this->successResponse(null, 'Program MBKM berhasil dihapus.');
    }

    /**
     * Lifecycle transition with server-side validation of the allowed graph.
     */
    public function transition(Request $request, MbkmProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ProgramStatus::values())],
            'notes' => ['nullable', 'string'],
        ]);

        $current = $program->status instanceof ProgramStatus
            ? $program->status
            : ProgramStatus::from((string) $program->status);

        $target = ProgramStatus::from($validated['status']);

        if (!$current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => ["Transisi program dari {$current->value} ke {$target->value} tidak diizinkan."],
            ]);
        }

        if ($target === ProgramStatus::PUBLISHED && $program->assessmentComponents()->count() === 0 && $program->requires_assessment) {
            throw ValidationException::withMessages([
                'status' => ['Program yang memerlukan penilaian harus memiliki komponen penilaian sebelum dipublikasikan.'],
            ]);
        }

        $program->update(['status' => $target]);

        $this->historyService->record(
            entity: $program,
            action: 'program.status_changed',
            fromStatus: $current->value,
            toStatus: $target->value,
            notes: $validated['notes'] ?? null,
            actorId: $request->user()?->id
        );

        return $this->successResponse($program->fresh(), 'Status program MBKM berhasil diperbarui.');
    }

    // ---------------------------------------------------------------------
    // Nested configuration
    // ---------------------------------------------------------------------

    public function storeLocation(Request $request, MbkmProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_mode' => ['required', 'string', 'in:on_campus,off_campus,domestic,overseas,remote,hybrid'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'is_remote' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $location = $program->locations()->create($validated);

        return $this->successResponse($location, 'Lokasi program berhasil ditambahkan.', 201);
    }

    public function destroyLocation(MbkmProgram $program, MbkmProgramLocation $location): JsonResponse
    {
        if ((int) $location->program_id !== (int) $program->id) {
            return $this->mbkmDeny('Lokasi tidak berasal dari program ini.');
        }

        $location->delete();

        return $this->successResponse(null, 'Lokasi program berhasil dihapus.');
    }

    public function storeRequirement(Request $request, MbkmProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:academic,administrative,document,custom'],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_mandatory' => ['nullable', 'boolean'],
            'is_document' => ['nullable', 'boolean'],
            'rule' => ['nullable', 'array'],
            'rule.field' => ['nullable', 'string', 'in:gpa,total_credits,current_semester,admission_year,study_program_id,student_status,passed_course_count'],
            'rule.operator' => ['nullable', 'string', 'in:>=,<=,>,<,==,!=,in,not_in'],
            'rule.value' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $requirement = $program->requirements()->create($validated);

        return $this->successResponse($requirement, 'Persyaratan program berhasil ditambahkan.', 201);
    }

    public function destroyRequirement(MbkmProgram $program, MbkmProgramRequirement $requirement): JsonResponse
    {
        if ((int) $requirement->program_id !== (int) $program->id) {
            return $this->mbkmDeny('Persyaratan tidak berasal dari program ini.');
        }

        $requirement->delete();

        return $this->successResponse(null, 'Persyaratan program berhasil dihapus.');
    }

    public function syncCriteria(Request $request, MbkmProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'criteria' => ['required', 'array'],
            'criteria.*.name' => ['required', 'string', 'max:255'],
            'criteria.*.description' => ['nullable', 'string'],
            'criteria.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'criteria.*.max_score' => ['nullable', 'numeric', 'min:1'],
            'criteria.*.sort_order' => ['nullable', 'integer'],
            'criteria.*.is_active' => ['nullable', 'boolean'],
        ]);

        $criteria = $this->selectionService->syncCriteria($program, $validated['criteria']);

        return $this->successResponse($criteria, 'Kriteria seleksi berhasil disimpan.');
    }

    public function syncAssessmentComponents(Request $request, MbkmProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'components' => ['required', 'array', 'min:1'],
            'components.*.code' => ['nullable', 'string', 'max:50'],
            'components.*.name' => ['required', 'string', 'max:255'],
            'components.*.type' => ['nullable', 'string', Rule::in(AssessmentComponentType::values())],
            'components.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'components.*.max_score' => ['nullable', 'numeric', 'min:1'],
            'components.*.assessor_type' => ['nullable', 'string', Rule::in(AssessorType::values())],
            'components.*.description' => ['nullable', 'string'],
            'components.*.sort_order' => ['nullable', 'integer'],
            'components.*.is_active' => ['nullable', 'boolean'],
        ]);

        $totalWeight = collect($validated['components'])->sum('weight');

        if (abs((float) $totalWeight - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'components' => ["Total bobot komponen penilaian harus 100% (saat ini {$totalWeight}%)."],
            ]);
        }

        DB::transaction(fn () => $this->assessmentService->syncComponents($program, $validated['components']));

        return $this->successResponse(
            MbkmAssessmentComponent::where('program_id', $program->id)->orderBy('sort_order')->get(),
            'Komponen penilaian berhasil disimpan.'
        );
    }

    /**
     * Student-facing catalog: only relevant programs, each annotated with the
     * student's own eligibility so the UI never offers a program that would be
     * rejected on submit.
     */
    public function catalog(Request $request): JsonResponse
    {
        $student = $this->requireStudent($request);

        $query = MbkmProgram::query()
            ->with(['programType', 'semester.academicYear', 'studyProgram', 'faculty', 'locations'])
            ->whereIn('status', ProgramStatus::publiclyVisible())
            ->orderByDesc('id');

        if ($request->filled('program_type_id')) {
            $query->where('program_type_id', $request->query('program_type_id'));
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->query('semester_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $programs = $query->limit(100)->get();

        $rows = $programs->map(function (MbkmProgram $program) use ($student) {
            $evaluation = $this->eligibilityService->evaluate($program, $student);

            return [
                'program' => $program,
                'is_eligible' => $evaluation['is_eligible'],
                'eligibility_reasons' => $evaluation['reasons'],
                'eligibility_checks' => $evaluation['checks'],
                'quota' => $evaluation['quota'],
                'participant_count' => $program->allow_public_participant_count
                    ? $program->participants()->count()
                    : null,
                'registration_open' => $program->isRegistrationOpen(),
                'has_active_application' => $this->eligibilityService->activeApplicationExists($program, $student),
            ];
        });

        return $this->successResponse($rows, 'Katalog program MBKM berhasil dimuat.');
    }

    /**
     * Explain a student's eligibility for one program (transparent status).
     */
    public function eligibility(Request $request, MbkmProgram $program): JsonResponse
    {
        $student = $this->requireStudent($request);

        $evaluation = $this->eligibilityService->evaluate(
            program: $program,
            student: $student,
            enforceDocuments: (bool) $program->requires_documents,
            uploadedDocumentCodes: \Modules\MBKM\Models\MbkmApplication::where('program_id', $program->id)
                ->where('student_id', $student->id)
                ->with('documents')
                ->get()
                ->flatMap(fn ($a) => $a->documents->pluck('category'))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        );

        return $this->successResponse($evaluation, 'Status kelayakan berhasil dihitung.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateProgram(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'program_type_id' => ['required', 'integer', 'exists:mbkm_program_types,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('mbkm_programs', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'organizer_type' => ['required', 'string', 'in:institution,faculty,study_program,academic_unit,external'],
            'organizer_name' => ['nullable', 'string', 'max:255'],
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'study_program_id' => ['nullable', 'integer', 'exists:study_programs,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'registration_start_date' => ['nullable', 'date'],
            'registration_end_date' => ['nullable', 'date', 'after_or_equal:registration_start_date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'target_degree_levels' => ['nullable', 'array'],
            'target_degree_levels.*' => ['string', 'in:D3,D4,S1,S2,S3'],
            'target_study_program_ids' => ['nullable', 'array'],
            'target_study_program_ids.*' => ['integer', 'exists:study_programs,id'],
            'target_admission_years' => ['nullable', 'array'],
            'target_admission_years.*' => ['integer', 'min:1990', 'max:2100'],
            'min_semester' => ['nullable', 'integer', 'min:1', 'max:14'],
            'max_semester' => ['nullable', 'integer', 'min:1', 'max:14', 'gte:min_semester'],
            'min_gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'min_credits' => ['nullable', 'integer', 'min:0'],
            'max_recognized_credits' => ['nullable', 'integer', 'min:0'],
            'participation_limit' => ['nullable', 'integer', 'min:1'],
            'required_passed_course_ids' => ['nullable', 'array'],
            'required_passed_course_ids.*' => ['integer', 'exists:courses,id'],
            'location_mode' => ['required', 'string', 'in:on_campus,off_campus,domestic,overseas,remote,hybrid'],
            'requires_documents' => ['nullable', 'boolean'],
            'requires_learning_agreement' => ['nullable', 'boolean'],
            'requires_attendance' => ['nullable', 'boolean'],
            'requires_logbook' => ['nullable', 'boolean'],
            'logbook_period' => ['nullable', 'string', 'in:daily,weekly,periodic'],
            'requires_assessment' => ['nullable', 'boolean'],
            'requires_final_report' => ['nullable', 'boolean'],
            'requires_recognition' => ['nullable', 'boolean'],
            'allow_public_participant_count' => ['nullable', 'boolean'],
            'min_attendance_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(ProgramStatus::values())],
            'requirements_text' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
