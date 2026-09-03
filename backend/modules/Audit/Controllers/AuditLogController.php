<?php

namespace Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Resources\AuditLogResource;

class AuditLogController extends Controller
{
    use HasApiResponse;

    /**
     * List audit logs with search, filtering, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user');

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['action', 'module', 'description', 'entity_type'],
            filterableColumns: ['action', 'module', 'user_id', 'entity_type', 'entity_id'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Audit logs retrieved successfully.',
            resourceClass: AuditLogResource::class
        );
    }

    /**
     * Display a specific audit log record.
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        return $this->successResponse(
            data: new AuditLogResource($auditLog->load('user')),
            message: 'Audit log retrieved successfully.'
        );
    }
}
