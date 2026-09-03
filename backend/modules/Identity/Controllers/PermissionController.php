<?php

namespace Modules\Identity\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Models\Permission;
use Modules\Identity\Resources\PermissionResource;

class PermissionController extends Controller
{
    use HasApiResponse;

    /**
     * List all permissions with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'display_name', 'group'],
            filterableColumns: ['group'],
            defaultSort: 'group',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Permissions retrieved successfully.',
            resourceClass: PermissionResource::class
        );
    }
}
