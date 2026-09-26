<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\IssueSeverity;
use Modules\MBKM\Enums\IssueStatus;
use Modules\MBKM\Models\MbkmIssue;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Services\MbkmHistoryService;
use Modules\MBKM\Services\MbkmNotificationService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * Issue / problem monitoring during MBKM execution.
 */
class MbkmIssueController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MbkmIssue::query()->with([
            'participant.program',
            'participant.student',
            'assignee.user:id,name',
        ]);

        // Scope through the shared helper instead of role branches: the branches
        // covered only students and plain lecturers, leaving every other role —
        // including a study-program-scoped manager (kaprodi) — unscoped.
        $visible = $this->visibleStudentIds($request);

        if ($visible !== null) {
            $query->whereHas('participant', fn ($q) => $q->whereIn('student_id', $visible));
        }

        if ($request->filled('participant_id')) {
            $query->where('participant_id', $request->query('participant_id'));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['title', 'description', 'category'],
            filterableColumns: ['severity', 'status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Data permasalahan MBKM berhasil dimuat.');
    }

    public function store(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke peserta MBKM ini.');
        }

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'severity' => ['required', 'string', Rule::in(IssueSeverity::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:lecturers,id'],
        ]);

        $issue = $participant->issues()->create(array_merge($validated, [
            'status' => IssueStatus::OPEN,
            'reported_by' => $request->user()?->id,
        ]));

        $this->historyService->record(
            entity: $participant,
            action: 'issue.created',
            notes: 'Permasalahan dicatat: ' . $issue->title,
            meta: ['issue_id' => $issue->id, 'severity' => $validated['severity']],
            actorId: $request->user()?->id
        );

        $this->notificationService->notifyPermissionHolders(
            'mbkm.participants.view',
            'issue.created',
            'Permasalahan MBKM Baru',
            $issue->title . ' (' . $validated['severity'] . ')',
            ['issue_id' => $issue->id, 'participant_id' => $participant->id]
        );

        return $this->successResponse($issue, 'Permasalahan berhasil dicatat.', 201);
    }

    public function update(Request $request, MbkmIssue $issue): JsonResponse
    {
        $issue->loadMissing('participant');

        if (!$this->mayAccessParticipant($request, $issue->participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke permasalahan ini.');
        }

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:50'],
            'severity' => ['nullable', 'string', Rule::in(IssueSeverity::values())],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:lecturers,id'],
            'status' => ['nullable', 'string', Rule::in(IssueStatus::values())],
            'resolution' => ['nullable', 'string'],
        ]);

        $from = $issue->status instanceof IssueStatus ? $issue->status->value : (string) $issue->status;

        if (isset($validated['status']) && in_array($validated['status'], [IssueStatus::RESOLVED->value, IssueStatus::CLOSED->value], true)) {
            $validated['resolved_at'] = now();
            $validated['resolved_by'] = $request->user()?->id;
        }

        $issue->update($validated);

        $this->historyService->record(
            entity: $issue->participant,
            action: 'issue.updated',
            fromStatus: $from,
            toStatus: $issue->status instanceof IssueStatus ? $issue->status->value : (string) $issue->status,
            notes: 'Permasalahan diperbarui.',
            meta: ['issue_id' => $issue->id],
            actorId: $request->user()?->id
        );

        return $this->successResponse($issue->fresh(['assignee']), 'Permasalahan berhasil diperbarui.');
    }

    public function destroy(Request $request, MbkmIssue $issue): JsonResponse
    {
        $issue->loadMissing('participant');

        if (!$this->mayAccessParticipant($request, $issue->participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke permasalahan ini.');
        }

        $issue->delete();

        return $this->successResponse(null, 'Permasalahan berhasil dihapus.');
    }
}
