<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\MBKM\Services\MbkmReportService;

/**
 * Reporting endpoints. Each report accepts the module-wide filters (periode,
 * fakultas, program studi, program, jenis program, mitra, status, angkatan,
 * semester, dosen pembimbing).
 */
class MbkmReportController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected MbkmReportService $reportService,
    ) {}

    public function types(): JsonResponse
    {
        return $this->successResponse(MbkmReportService::types(), 'Jenis laporan MBKM.');
    }

    public function show(Request $request, string $type): JsonResponse
    {
        if (!in_array($type, MbkmReportService::types(), true)) {
            return $this->errorResponse('Jenis laporan tidak dikenal.', 404);
        }

        return $this->successResponse(
            $this->reportService->build($type, $request),
            'Laporan MBKM berhasil dibuat.'
        );
    }

    /**
     * Download a report as CSV. Accepts exactly the same filters as `show`, so
     * what the user sees on screen is what lands in the file.
     */
    public function export(Request $request, string $type): StreamedResponse|JsonResponse
    {
        if (!in_array($type, MbkmReportService::types(), true)) {
            return $this->errorResponse('Jenis laporan tidak dikenal.', 404);
        }

        $table = $this->reportService->tabulate($type, $request);
        $filename = 'laporan-mbkm-' . $type . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file with the right encoding.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $table['headers']);

            foreach ($table['rows'] as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
