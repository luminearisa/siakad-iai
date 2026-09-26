<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Enums\MbkmAttendanceStatus;
use Modules\MBKM\Models\MbkmAttendance;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Services\MbkmAttendanceService;
use Modules\MBKM\Traits\MbkmAuthorization;

/**
 * MBKM attendance. A distinct boundary from the regular lecture attendance engine
 * (see MbkmAttendanceService) and duplicate-safe per participant/date.
 */
class MbkmAttendanceController extends Controller
{
    use HasApiResponse, MbkmAuthorization;

    public function __construct(
        protected MbkmAttendanceService $service,
    ) {}

    public function index(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke presensi peserta ini.');
        }

        $query = $participant->attendances();

        if ($request->filled('from')) {
            $query->where('attendance_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->where('attendance_date', '<=', $request->query('to'));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['notes'],
            filterableColumns: ['status'],
            defaultSort: 'attendance_date',
            defaultDirection: 'desc'
        );

        return $this->successResponse(
            data: $paginator->items(),
            message: 'Presensi MBKM berhasil dimuat.',
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'summary' => $this->service->summary($participant),
            ]
        );
    }

    public function store(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayWriteParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak mencatat presensi peserta ini.');
        }

        $this->service->assertParticipantAcceptsAttendance($participant);

        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(MbkmAttendanceStatus::values())],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'notes' => ['nullable', 'string'],
        ]);

        $attendance = $this->service->record($participant, $validated, $request->user());

        return $this->successResponse($attendance, 'Presensi MBKM berhasil dicatat.', 201);
    }

    /**
     * Batch (sheet) input.
     */
    public function batch(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayWriteParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak berhak mencatat presensi peserta ini.');
        }

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.attendance_date' => ['required', 'date'],
            'rows.*.status' => ['required', 'string', Rule::in(MbkmAttendanceStatus::values())],
            'rows.*.check_in_time' => ['nullable', 'date_format:H:i'],
            'rows.*.check_out_time' => ['nullable', 'date_format:H:i'],
            'rows.*.duration_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rows.*.notes' => ['nullable', 'string'],
        ]);

        $recorded = $this->service->recordBatch($participant, $validated['rows'], $request->user());

        return $this->successResponse(
            ['recorded' => count($recorded), 'summary' => $this->service->summary($participant)],
            'Presensi MBKM berhasil disimpan.'
        );
    }

    public function summary(Request $request, MbkmParticipant $participant): JsonResponse
    {
        if (!$this->mayAccessParticipant($request, $participant)) {
            return $this->mbkmDeny('Anda tidak memiliki akses ke presensi peserta ini.');
        }

        return $this->successResponse([
            'summary' => $this->service->summary($participant),
            'meets_minimum' => $this->service->meetsMinimum($participant),
            'minimum_percentage' => $participant->program?->min_attendance_percentage,
        ], 'Rekap presensi MBKM.');
    }

    public function destroy(Request $request, MbkmAttendance $attendance): JsonResponse
    {
        $attendance->loadMissing('participant');

        if (!$this->mayWriteParticipant($request, $attendance->participant)) {
            return $this->mbkmDeny('Anda tidak berhak menghapus presensi ini.');
        }

        $attendance->delete();

        return $this->successResponse(null, 'Presensi berhasil dihapus.');
    }
}
