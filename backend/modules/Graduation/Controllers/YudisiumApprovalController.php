<?php

namespace Modules\Graduation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Graduation\Models\YudisiumParticipant;

class YudisiumApprovalController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $periodId = $request->query('yudisium_period_id');

        // Calculate 5 metric cards
        $statsQuery = YudisiumParticipant::query();
        if ($periodId) {
            $statsQuery->where('yudisium_period_id', $periodId);
        }

        $stats = [
            'ready' => (clone $statsQuery)->where('status', 'ready')->count(),
            'needs_revision' => (clone $statsQuery)->where('status', 'needs_revision')->count(),
            'submitted' => (clone $statsQuery)->where('status', 'submitted')->count(),
            're_review' => (clone $statsQuery)->where('status', 're_review')->count(),
            'under_review' => (clone $statsQuery)->where('status', 'under_review')->count(),
        ];

        // Applications list
        $query = YudisiumParticipant::with([
            'student.studyProgram',
            'student.user',
            'period',
            'thesis',
            'documentSubmissions.requirement',
        ])->orderBy('id', 'desc');

        if ($periodId) {
            $query->where('yudisium_period_id', $periodId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->whereHas('student', function ($q) use ($s) {
                $q->where('full_name', 'like', "%{$s}%")
                    ->orWhere('student_number', 'like', "%{$s}%");
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $participants = $query->paginate($perPage);

        return $this->successResponse(
            data: $participants->items(),
            message: 'Daftar persetujuan yudisium berhasil dimuat.',
            meta: [
                'stats' => $stats,
                'current_page' => $participants->currentPage(),
                'last_page' => $participants->lastPage(),
                'per_page' => $participants->perPage(),
                'total' => $participants->total(),
            ]
        );
    }

    public function updateStatus(Request $request, YudisiumParticipant $participant): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:submitted,under_review,needs_revision,re_review,ready,passed,rejected'],
            'rejection_reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $participant->update($validated);

        return $this->successResponse(
            data: $participant->fresh(['student.studyProgram', 'period', 'thesis']),
            message: 'Status persetujuan yudisium berhasil diperbarui.'
        );
    }

    public function finalizeParticipants(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'yudisium_period_id' => ['nullable', 'integer', 'exists:yudisium_periods,id'],
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', 'exists:yudisium_participants,id'],
        ]);

        $query = YudisiumParticipant::query();

        if (! empty($validated['participant_ids'])) {
            $query->whereIn('id', $validated['participant_ids']);
        } elseif (! empty($validated['yudisium_period_id'])) {
            $query->where('yudisium_period_id', $validated['yudisium_period_id'])
                ->where('status', 'ready');
        } else {
            $query->where('status', 'ready');
        }

        $count = $query->update(['status' => 'passed']);

        return $this->successResponse(
            data: null,
            message: "{$count} mahasiswa berhasil ditetapkan sebagai peserta yudisium."
        );
    }
}
