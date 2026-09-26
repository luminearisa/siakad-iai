<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MBKM\Enums\DocumentStatus;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmDocument;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Services\MbkmHistoryService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Single document store for the whole MBKM module: upload, list, verify/reject,
 * and download metadata. No second storage layer is introduced anywhere else.
 */
class MbkmDocumentController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmHistoryService $historyService,
    ) {}

    /**
     * Upload a document for a given MBKM entity.
     *
     * POST /mbkm/documents
     * body: documentable_type (program|application|participant|cooperation|learning_agreement|activity_log|issue),
     *       documentable_id, category, file
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documentable_type' => ['required', 'string', 'in:program,application,participant,cooperation,learning_agreement,activity_log,issue'],
            'documentable_id' => ['required', 'integer'],
            'category' => ['required', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip'],
        ]);

        $modelClass = $this->resolveModelClass($validated['documentable_type']);
        $entity = $modelClass::findOrFail($validated['documentable_id']);

        // Students may only upload to their own application/participant.
        if ($this->isMbkmStudent($request)) {
            if ($denied = $this->denyStudentUpload($request, $entity)) {
                return $denied;
            }
        } elseif (!$this->mayUploadToEntity($request, $entity)) {
            // Staff are scoped too: holding a generic authenticated session is
            // not enough to attach files to an arbitrary MBKM entity.
            return $this->mbkmDeny('Anda tidak berhak mengunggah dokumen untuk entitas ini.');
        }

        $file = $request->file('file');
        $path = $file->store('mbkm/documents', 'public');

        $document = $entity->documents()->create([
            'category' => $validated['category'],
            'title' => $validated['title'] ?? $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'status' => DocumentStatus::UPLOADED,
            'uploaded_by' => $request->user()?->id,
            'expires_at' => $validated['expires_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->historyService->record(
            entity: $entity,
            action: 'document.uploaded',
            notes: 'Dokumen ' . $validated['category'] . ' diunggah.',
            meta: ['document_id' => $document->id, 'category' => $validated['category']],
        );

        return $this->successResponse($document, 'Dokumen berhasil diunggah.', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documentable_type' => ['nullable', 'string'],
            'documentable_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ]);

        $query = MbkmDocument::query()->with(['uploader:id,name', 'verifier:id,name']);

        // Scope BEFORE any filter: this endpoint is not bound to a single
        // participant, so without this every authenticated user could list
        // every student's KTM / CV / certificate.
        $visibleStudentIds = $this->visibleStudentIds($request);

        if ($visibleStudentIds !== null) {
            $query->where(function ($q) use ($visibleStudentIds) {
                $q->where(fn ($qq) => $qq
                    ->where('documentable_type', MbkmApplication::class)
                    ->whereIn('documentable_id', MbkmApplication::whereIn('student_id', $visibleStudentIds)->select('id')))
                    ->orWhere(fn ($qq) => $qq
                        ->where('documentable_type', MbkmParticipant::class)
                        ->whereIn('documentable_id', MbkmParticipant::whereIn('student_id', $visibleStudentIds)->select('id')));
            });
        }

        if (!empty($validated['documentable_type']) && !empty($validated['documentable_id'])) {
            $query->where('documentable_type', $this->resolveModelClass($validated['documentable_type']))
                ->where('documentable_id', $validated['documentable_id']);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return $this->successResponse(
            $query->orderByDesc('id')->limit(200)->get(),
            'Dokumen MBKM berhasil dimuat.'
        );
    }

    public function verify(Request $request, MbkmDocument $document): JsonResponse
    {
        if (!$this->mayTouchDocument($request, $document)) {
            return $this->mbkmDeny('Anda tidak berhak memverifikasi dokumen ini.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:verified,rejected,expired,uploaded'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $document->update([
            'status' => $validated['status'],
            'verified_by' => $request->user()?->id,
            'verified_at' => now(),
            'rejection_reason' => $validated['status'] === 'rejected' ? ($validated['rejection_reason'] ?? null) : null,
        ]);

        $this->historyService->record(
            entity: $document,
            action: 'document.' . $validated['status'],
            notes: $validated['rejection_reason'] ?? null,
            meta: ['document_id' => $document->id],
        );

        return $this->successResponse($document->fresh(), 'Status dokumen berhasil diperbarui.');
    }

    public function destroy(Request $request, MbkmDocument $document): JsonResponse
    {
        if (!$this->mayTouchDocument($request, $document)) {
            return $this->mbkmDeny('Anda tidak berhak menghapus dokumen ini.');
        }

        $document->delete();

        return $this->successResponse(null, 'Dokumen berhasil dihapus.');
    }

    /**
     * May the actor verify/delete this document? Program- and cooperation-level
     * documents are module configuration; everything else belongs to a student
     * and follows the student visibility scope.
     */
    protected function mayTouchDocument(Request $request, MbkmDocument $document): bool
    {
        $studentId = $this->documentOwnerStudentId($document);

        if ($studentId === null) {
            return (bool) $this->currentUser($request)?->hasPermissionTo('mbkm.manage');
        }

        return $this->maySeeStudent($request, $studentId);
    }

    /**
     * May the actor attach a document to this entity?
     */
    protected function mayUploadToEntity(Request $request, object $entity): bool
    {
        // Program / cooperation documents are module configuration.
        if ($entity instanceof \Modules\MBKM\Models\MbkmProgram
            || $entity instanceof \Modules\MBKM\Models\MbkmCooperation) {
            return (bool) $this->currentUser($request)?->hasPermissionTo('mbkm.manage');
        }

        $studentId = $this->entityOwnerStudentId($entity);

        if ($studentId === null) {
            return (bool) $this->currentUser($request)?->hasPermissionTo('mbkm.manage');
        }

        return $this->maySeeStudent($request, $studentId);
    }

    /**
     * Resolve the owning student of a documentable entity, if any.
     */
    protected function documentOwnerStudentId(MbkmDocument $document): ?int
    {
        $class = $document->documentable_type;
        $entity = $class::find($document->documentable_id);

        return $entity ? $this->entityOwnerStudentId($entity) : null;
    }

    protected function entityOwnerStudentId(object $entity): ?int
    {
        if ($entity instanceof \Modules\MBKM\Models\MbkmApplication
            || $entity instanceof \Modules\MBKM\Models\MbkmParticipant) {
            return $entity->student_id !== null ? (int) $entity->student_id : null;
        }

        // Learning agreement / activity log / issue hang off a participant.
        if ($entity instanceof \Modules\MBKM\Models\MbkmLearningAgreement
            || $entity instanceof \Modules\MBKM\Models\MbkmActivityLog
            || $entity instanceof \Modules\MBKM\Models\MbkmIssue) {
            $participant = $entity->participant;

            return $participant?->student_id !== null ? (int) $participant->student_id : null;
        }

        return null;
    }

    protected function resolveModelClass(string $type): string
    {
        return match ($type) {
            'program' => \Modules\MBKM\Models\MbkmProgram::class,
            'application' => \Modules\MBKM\Models\MbkmApplication::class,
            'participant' => \Modules\MBKM\Models\MbkmParticipant::class,
            'cooperation' => \Modules\MBKM\Models\MbkmCooperation::class,
            'learning_agreement' => \Modules\MBKM\Models\MbkmLearningAgreement::class,
            'activity_log' => \Modules\MBKM\Models\MbkmActivityLog::class,
            'issue' => \Modules\MBKM\Models\MbkmIssue::class,
        };
    }

    /**
     * A student may only attach documents to their own application/participant.
     */
    protected function denyStudentUpload(Request $request, object $entity): ?JsonResponse
    {
        $student = $this->currentStudent($request);

        if (!$student) {
            return $this->mbkmDeny('Profil mahasiswa tidak ditemukan.');
        }

        if ($entity instanceof \Modules\MBKM\Models\MbkmApplication && (int) $entity->student_id === (int) $student->id) {
            return null;
        }

        if ($entity instanceof \Modules\MBKM\Models\MbkmParticipant && (int) $entity->student_id === (int) $student->id) {
            return null;
        }

        return $this->mbkmDeny('Anda hanya dapat mengunggah dokumen untuk pendaftaran/peserta MBKM milik Anda.');
    }
}
