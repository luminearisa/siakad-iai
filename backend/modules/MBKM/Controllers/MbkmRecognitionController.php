<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\RecognitionStatus;
use Modules\MBKM\Enums\RecognitionType;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmRecognition;
use Modules\MBKM\Services\MbkmAcademicIntegrationService;
use Modules\MBKM\Services\MbkmHistoryService;
use Modules\MBKM\Services\MbkmRecognitionService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Recognition / credit conversion.
 *
 * Workflow: draft -> submitted -> reviewed -> approved -> locked.
 * On approval the row is pushed into the existing academic pipeline (KRS +
 * student_grades) so it surfaces in KHS and the transcript.
 */
class MbkmRecognitionController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmRecognitionService $service,
        protected MbkmAcademicIntegrationService $integrationService,
        protected MbkmHistoryService $historyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MbkmRecognition::query()->with([
            'participant.student.studyProgram',
            'participant.program',
            'course',
            'curriculum',
            'semester',
            'activityLog',
        ]);

        // Same scoping rule as the participant list: role branches alone leave
        // every *other* role unscoped. This endpoint has no permission
        // middleware, so a user with no mbkm.* permission at all would otherwise
        // list every recognition in the institution.
        $visible = $this->visibleStudentIds($request);

        if ($visible !== null) {
            $query->whereHas('participant', fn ($q) => $q->whereIn('student_id', $visible));
        }

        if ($request->filled('participant_id')) {
            $query->where('participant_id', $request->query('participant_id'));
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->query('program_id'));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['source_label'],
            filterableColumns: ['status', 'sync_status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Data rekognisi MBKM berhasil dimuat.');
    }

    /**
     * Recognition rows + totals for a participant (the one-to-many breakdown).
     */
    public function indexForParticipant(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke rekognisi peserta ini.');
        }

        $participant->loadMissing(['program']);

        return $this->successResponse([
            'summary' => $this->service->summary($participant),
            'recognitions' => $participant->recognitions()->with(['course', 'semester', 'activityLog'])->orderBy('id')->get(),
            'max_recognized_credits' => $participant->program?->max_recognized_credits,
            'recognized_credits' => $participant->recognized_credits,
            'academic_result' => $this->integrationService->academicResultForParticipant($participant),
        ], 'Rekognisi peserta MBKM.');
    }

    public function store(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayWriteParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak menyusun rekognisi peserta ini.');
        }

        $validated = $this->validatePayload($request);

        $recognition = $this->service->create($participant, $validated, $request->user());

        return $this->successResponse($recognition, 'Rekognisi MBKM berhasil dibuat.', 201);
    }

    public function update(Request $request, MbkmRecognition $recognition): JsonResponse
    {
        $recognition->loadMissing('participant');

        if (!$this->mayWriteParticipant($request, $recognition->participant)) {
            return $this->mbkmDeny('Anda tidak berhak mengubah rekognisi ini.');
        }

        $updated = $this->service->update($recognition, $this->validatePayload($request, partial: true), $request->user());

        return $this->successResponse($updated, 'Rekognisi MBKM berhasil diperbarui.');
    }

    public function transition(Request $request, MbkmRecognition $recognition): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(RecognitionStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->service->transition($recognition, $validated['status'], $request->user(), $validated['notes'] ?? null);

        return $this->successResponse($updated, 'Status rekognisi MBKM berhasil diperbarui.');
    }

    /**
     * Formal correction of an approved recognition (keeps the audit trail).
     */
    public function correct(Request $request, MbkmRecognition $recognition): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $updated = $this->service->requestCorrection($recognition, $request->user(), $validated['reason']);

        return $this->successResponse($updated, 'Rekognisi dibuka kembali untuk koreksi.');
    }

    public function show(Request $request, MbkmRecognition $recognition): JsonResponse
    {
        $recognition->loadMissing('participant');

        if (!$this->mayAccessParticipant($request, $recognition->participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke rekognisi ini.');
        }

        return $this->successResponse([
            'recognition' => $recognition->load(['course', 'curriculum', 'curriculumSubject', 'semester', 'activityLog', 'participant.student']),
            'history' => $this->historyService->forEntity($recognition),
        ], 'Detail rekognisi MBKM.');
    }

    public function destroy(Request $request, MbkmRecognition $recognition): JsonResponse
    {
        $recognition->loadMissing('participant');

        if (!$this->mayWriteParticipant($request, $recognition->participant)) {
            return $this->mbkmDeny('Anda tidak berhak menghapus rekognisi ini.');
        }

        if ($recognition->isLocked()) {
            return $this->errorResponse('Rekognisi yang sudah disetujui tidak dapat dihapus. Gunakan alur koreksi.', 422);
        }

        $recognition->delete();

        return $this->successResponse(null, 'Rekognisi MBKM berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'course_id' => [$required, 'integer', 'exists:courses,id'],
            'curriculum_id' => ['nullable', 'integer', 'exists:curricula,id'],
            'curriculum_subject_id' => ['nullable', 'integer', 'exists:curriculum_subjects,id'],
            'activity_log_id' => ['nullable', 'integer', 'exists:mbkm_activity_logs,id'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'credits' => ['nullable', 'integer', 'min:1', 'max:24'],
            'recognition_type' => ['nullable', 'string', Rule::in(RecognitionType::values())],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
