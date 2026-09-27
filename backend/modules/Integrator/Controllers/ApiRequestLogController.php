<?php

namespace Modules\Integrator\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Integrator\Models\ApiRequestLog;
use Modules\Integrator\Resources\ApiRequestLogResource;

/**
 * Read-only view of the integration access log.
 *
 * Since the endpoints can return personal data, this is the screen an operator
 * opens when asked "who pulled what, when, and did they have the right scope?".
 */
class ApiRequestLogController extends Controller
{
    use HasApiResponse;

    /**
     * Paginated request log.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiRequestLog::query()
            ->with(['client:id,name,slug', 'key:id,name,key_prefix,api_client_id']);

        if ($request->has('successful')) {
            $request->boolean('successful')
                ? $query->whereBetween('status_code', [200, 299])
                : $query->whereNotBetween('status_code', [200, 299]);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['path', 'ip_address', 'error_message'],
            filterableColumns: ['api_client_id', 'api_key_id', 'status_code', 'method'],
            defaultSort: 'created_at',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'API request log retrieved successfully.',
            resourceClass: ApiRequestLogResource::class
        );
    }

    /**
     * Aggregated numbers for the integrator dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 7);
        $days = max(1, min($days, 90));

        $since = now()->subDays($days);

        $totals = ApiRequestLog::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as successful')
            ->selectRaw('SUM(CASE WHEN status_code BETWEEN 400 AND 499 THEN 1 ELSE 0 END) as client_errors')
            ->selectRaw('SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as server_errors')
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->first();

        $total = (int) ($totals->total ?? 0);

        return $this->successResponse(
            data: [
                'window_days' => $days,
                'totals' => [
                    'requests' => $total,
                    'successful' => (int) ($totals->successful ?? 0),
                    'client_errors' => (int) ($totals->client_errors ?? 0),
                    'server_errors' => (int) ($totals->server_errors ?? 0),
                    'success_rate' => $total > 0 ? round(((int) $totals->successful / $total) * 100, 2) : 0.0,
                    'avg_duration_ms' => round((float) ($totals->avg_duration_ms ?? 0), 2),
                ],
                'top_paths' => ApiRequestLog::query()
                    ->where('created_at', '>=', $since)
                    ->select('path')
                    ->selectRaw('COUNT(*) as hits')
                    ->groupBy('path')
                    ->orderByDesc('hits')
                    ->limit(10)
                    ->get()
                    ->map(fn ($row) => ['path' => $row->path, 'hits' => (int) $row->hits]),
                'per_client' => DB::table('api_request_logs')
                    ->leftJoin('api_clients', 'api_clients.id', '=', 'api_request_logs.api_client_id')
                    ->where('api_request_logs.created_at', '>=', $since)
                    ->select('api_clients.name as client', 'api_clients.slug as slug')
                    ->selectRaw('COUNT(*) as hits')
                    ->selectRaw('SUM(CASE WHEN api_request_logs.status_code BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as successful')
                    ->groupBy('api_clients.name', 'api_clients.slug')
                    ->orderByDesc('hits')
                    ->get(),
                'recent_errors' => ApiRequestLog::query()
                    ->with('client:id,name,slug')
                    ->where('created_at', '>=', $since)
                    ->where('status_code', '>=', 400)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (ApiRequestLog $log) => [
                        'id' => $log->id,
                        'path' => $log->path,
                        'status_code' => $log->status_code,
                        'error_message' => $log->error_message,
                        'client' => $log->client?->slug,
                        'created_at' => $log->created_at?->toIso8601String(),
                    ]),
            ],
            message: 'API request statistics retrieved successfully.'
        );
    }
}
