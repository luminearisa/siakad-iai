<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\WithdrawalType;
use Modules\MBKM\Models\MbkmCompletion;
use Modules\MBKM\Models\MbkmExtensionRequest;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmWithdrawalRequest;
use Modules\MBKM\Services\MbkmCompletionService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Completion verification, withdrawal/cancellation/termination, and extension.
 */
class MbkmCompletionController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmCompletionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MbkmCompletion::query()->with([
            'participant.program',
            'participant.student.studyProgram',
            'verifier:id,name',
        ]);

        if ($this->isMbkmStudent($request)) {
            $student = $this->currentStudent($request);
            $query->whereHas('participant', fn ($q) => $q->where('student_id', $student?->id));
        } elseif ($this->isMbkmLecturerOnly($request)) {
            $lecturerId = $this->currentLecturer($request)?->id;
            $query->whereHas('participant.supervisors', fn ($q) => $q->where('lecturer_id', $lecturerId));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: [],
            filterableColumns: ['status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Data penyelesaian MBKM berhasil dimuat.');
    }

    /**
     * Evaluate (dry-run) the completion requirements of a participant.
     */
    public function evaluate(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke data penyelesaian peserta ini.');
        }

        return $this->successResponse($this->service->evaluate($participant), 'Evaluasi penyelesaian MBKM.');
    }

    /**
     * Run the completion verification and persist it.
     */
    public function verify(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak memverifikasi penyelesaian peserta ini.');
        }

        $validated = $request->validate([
            'force' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $completion = $this->service->verify(
            participant: $participant,
            actor: $request->user(),
            force: (bool) ($validated['force'] ?? false),
            notes: $validated['notes'] ?? null
        );

        return $this->successResponse($completion, 'Verifikasi penyelesaian MBKM selesai dijalankan.');
    }

    /**
     * Attach the completion certificate / letter.
     */
    public function attachCertificate(Request $request, MbkmParticipant $participant): JsonResponse
    {
        // Check authorisation *before* touching the disk, otherwise an
        // unauthorised caller could still write files into public storage.
        if (!$this->mayManageParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak mengunggah dokumen penyelesaian peserta ini.');
        }

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $file = $request->file('file');
        $path = $file->store('mbkm/completion', 'public');

        $document = $this->service->attachCertificate($participant, [
            'category' => $validated['category'] ?? 'certificate',
            'title' => $validated['title'] ?? $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ], $request->user());

        return $this->successResponse($document, 'Dokumen penyelesaian berhasil diunggah.', 201);
    }

    // -----------------------------------------------------------------
    // Withdrawal / cancellation / termination
    // -----------------------------------------------------------------

    public function indexWithdrawals(Request $request): JsonResponse
    {
        $query = MbkmWithdrawalRequest::query()->with(['participant.program', 'participant.student']);

        // Scope BEFORE any filter. Scoping only the student case left a plain
        // lecturer (and any authenticated user without an MBKM role) seeing
        // every withdrawal request in the institution.
        $visible = $this->visibleStudentIds($request);

        if ($visible !== null) {
            $query->whereHas('participant', fn ($q) => $q->whereIn('student_id', $visible));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['reason'],
            filterableColumns: ['status', 'type'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Data pengunduran diri MBKM berhasil dimuat.');
    }

    /**
     * Student (or manager) files a withdrawal / cancellation / termination request.
     */
    public function storeWithdrawal(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke peserta MBKM ini.');
        }

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(WithdrawalType::values())],
            'reason' => ['required', 'string', 'max:2000'],
            'effective_date' => ['nullable', 'date'],
            'document_id' => ['nullable', 'integer', 'exists:mbkm_documents,id'],
        ]);

        // `exists:` only proves the document exists somewhere in the module.
        // Without an ownership check a student could attach another student's
        // document to their own request, exposing it to the reviewer.
        if (!empty($validated['document_id'])) {
            $owns = $participant->documents()->whereKey($validated['document_id'])->exists();

            if (!$owns) {
                return $this->errorResponse('Dokumen pendukung tidak terdaftar pada peserta ini.', 422);
            }
        }

        $withdrawal = $participant->withdrawalRequests()->create(array_merge($validated, [
            'status' => 'pending',
            'requested_by' => $request->user()?->id,
        ]));

        return $this->successResponse($withdrawal, 'Permohonan berhasil diajukan.', 201);
    }

    public function decideWithdrawal(Request $request, MbkmWithdrawalRequest $withdrawal): JsonResponse
    {
        $withdrawal->loadMissing('participant');

        // Deciding a withdrawal mutates the participant's lifecycle status
        // (withdrawn / terminated), so it is an administrative write: a manager,
        // one of the participant's supervisors, or a manager scoped to the
        // participant's study program. Without this the endpoint accepted any
        // authenticated user — including the student themself.
        if (!$withdrawal->participant || !$this->mayManageParticipant($request, $withdrawal->participant)) {
            return $this->mbkmDeny('Anda tidak berhak memutuskan permohonan pengunduran diri peserta ini.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // A decided request must not be decidable again: each re-decision
        // rewrites the participant status and appends another history row.
        $current = $withdrawal->status instanceof \BackedEnum
            ? $withdrawal->status->value
            : (string) $withdrawal->status;

        if ($current !== 'pending') {
            return $this->errorResponse(
                'Permohonan ini sudah diputuskan (' . $current . ') dan tidak dapat diputuskan ulang.',
                422
            );
        }

        $decided = $this->service->decideWithdrawal($withdrawal, $request->user(), $validated['decision'], $validated['notes'] ?? null);

        return $this->successResponse($decided, 'Keputusan berhasil disimpan.');
    }

    // -----------------------------------------------------------------
    // Extension
    // -----------------------------------------------------------------

    public function indexExtensions(Request $request): JsonResponse
    {
        $query = MbkmExtensionRequest::query()->with(['participant.program', 'participant.student']);

        // Same scoping rule as indexWithdrawals(): scope before filtering, and
        // do not leave the non-student case unrestricted.
        $visible = $this->visibleStudentIds($request);

        if ($visible !== null) {
            $query->whereHas('participant', fn ($q) => $q->whereIn('student_id', $visible));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['reason'],
            filterableColumns: ['status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Data perpanjangan MBKM berhasil dimuat.');
    }

    public function storeExtension(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke peserta MBKM ini.');
        }

        $validated = $request->validate([
            'new_end_date' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $extension = $participant->extensionRequests()->create([
            'old_end_date' => $participant->end_date,
            'new_end_date' => $validated['new_end_date'],
            'reason' => $validated['reason'],
            'status' => 'pending',
            'requested_by' => $request->user()?->id,
        ]);

        return $this->successResponse($extension, 'Permohonan perpanjangan berhasil diajukan.', 201);
    }

    public function decideExtension(Request $request, MbkmExtensionRequest $extension): JsonResponse
    {
        $extension->loadMissing('participant');

        // Extending a placement changes the participant's end date, so it is an
        // administrative write: scope it to the actor like every other one.
        if (!$extension->participant || !$this->mayManageParticipant($request, $extension->participant)) {
            return $this->mbkmDeny('Anda tidak berhak memutuskan permohonan perpanjangan peserta ini.');
        }

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $current = $extension->status instanceof \BackedEnum
            ? $extension->status->value
            : (string) $extension->status;

        if ($current !== 'pending') {
            return $this->errorResponse(
                'Permohonan ini sudah diputuskan (' . $current . ') dan tidak dapat diputuskan ulang.',
                422
            );
        }

        $decided = $this->service->decideExtension($extension, $request->user(), $validated['decision'], $validated['notes'] ?? null);

        return $this->successResponse($decided, 'Keputusan perpanjangan berhasil disimpan.');
    }
}
