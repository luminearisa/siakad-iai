<?php

namespace Modules\Identity\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Models\Role;
use Modules\Identity\Requests\CreateRoleRequest;
use Modules\Identity\Requests\UpdateRoleRequest;
use Modules\Identity\Resources\RoleResource;

class RoleController extends Controller
{
    use HasApiResponse;

    /**
     * List roles with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::with('permissions');

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'display_name'],
            filterableColumns: ['is_system'],
            defaultSort: 'id',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Roles retrieved successfully.',
            resourceClass: RoleResource::class
        );
    }

    /**
     * Store a newly created role.
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        $role = Role::create([
            'name' => $request->validated('name'),
            'display_name' => $request->validated('display_name'),
            'description' => $request->validated('description'),
            'is_system' => false,
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->validated('permissions'));
        }

        return $this->successResponse(
            data: new RoleResource($role->load('permissions')),
            message: 'Role created successfully.',
            code: 201
        );
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        return $this->successResponse(
            data: new RoleResource($role->load('permissions')),
            message: 'Role retrieved successfully.'
        );
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        if ($role->is_system && $request->has('name') && $request->validated('name') !== $role->name) {
            return $this->errorResponse('System role name cannot be modified.', 422);
        }

        $role->update($request->only(['name', 'display_name', 'description']));

        if ($request->has('permissions')) {
            $role->syncPermissions($request->validated('permissions'));
        }

        return $this->successResponse(
            data: new RoleResource($role->fresh('permissions')),
            message: 'Role updated successfully.'
        );
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->errorResponse('System roles cannot be deleted.', 422);
        }

        $role->delete();

        return $this->successResponse(
            data: null,
            message: 'Role deleted successfully.'
        );
    }
}
