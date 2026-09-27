<?php

namespace Modules\Integrator\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Audit\Services\AuditService;
use Modules\Integrator\Models\ApiClient;
use Modules\Integrator\Requests\StoreApiClientRequest;
use Modules\Integrator\Requests\UpdateApiClientRequest;
use Modules\Integrator\Resources\ApiClientResource;
use Modules\Integrator\Services\ApiKeyService;

/**
 * Staff-facing CRUD for integration clients (the systems allowed to pull data).
 *
 * Route access is guarded by `permission:integrator.clients.*`; every mutation is
 * also written to the audit log with the acting user.
 */
class ApiClientController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected ApiKeyService $apiKeyService
    ) {}

    /**
     * Paginated client list.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiClient::query()
            ->with('creator:id,name')
            ->withCount(['keys', 'activeKeys']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'slug', 'contact_email'],
            filterableColumns: ['is_active'],
            defaultSort: 'name',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'API clients retrieved successfully.',
            resourceClass: ApiClientResource::class
        );
    }

    /**
     * Create a client.
     */
    public function store(StoreApiClientRequest $request): JsonResponse
    {
        $data = $request->validated();

        $client = ApiClient::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'allowed_ips' => $data['allowed_ips'] ?? null,
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? $this->apiKeyService->defaultRateLimit(),
            'created_by' => $request->user()?->id,
        ]);

        AuditService::log(
            action: 'created',
            module: 'integrator',
            description: "API client {$client->slug} was created.",
            entity: $client,
            newValues: $client->only(['name', 'slug', 'is_active', 'rate_limit_per_minute'])
        );

        return $this->successResponse(
            data: new ApiClientResource($client->loadCount(['keys', 'activeKeys'])),
            message: 'API client created successfully.',
            code: 201
        );
    }

    /**
     * Show one client with its keys.
     */
    public function show(ApiClient $client): JsonResponse
    {
        $client->load(['creator:id,name', 'keys' => fn ($query) => $query->orderByDesc('id')])
            ->loadCount(['keys', 'activeKeys']);

        return $this->successResponse(
            data: new ApiClientResource($client),
            message: 'API client retrieved successfully.'
        );
    }

    /**
     * Update a client (name, contact, allow-list, rate limit, activation).
     */
    public function update(UpdateApiClientRequest $request, ApiClient $client): JsonResponse
    {
        $data = $request->validated();
        $before = $client->only(['name', 'slug', 'is_active', 'allowed_ips', 'rate_limit_per_minute']);

        $client->fill($data)->save();

        AuditService::log(
            action: 'updated',
            module: 'integrator',
            description: "API client {$client->slug} was updated.",
            entity: $client,
            oldValues: $before,
            newValues: $client->only(['name', 'slug', 'is_active', 'allowed_ips', 'rate_limit_per_minute'])
        );

        return $this->successResponse(
            data: new ApiClientResource($client->loadCount(['keys', 'activeKeys'])),
            message: 'API client updated successfully.'
        );
    }

    /**
     * Delete a client that has no live credentials.
     *
     * A client with active keys must be deactivated instead: deleting it would
     * cascade the keys away and destroy the audit trail of what pulled the data.
     */
    public function destroy(ApiClient $client): JsonResponse
    {
        if ($client->activeKeys()->exists()) {
            return $this->errorResponse(
                message: 'Client masih memiliki API key aktif. Cabut key terlebih dahulu atau nonaktifkan client.',
                code: 422
            );
        }

        $identifier = $client->slug;
        $client->delete();

        AuditService::log(
            action: 'deleted',
            module: 'integrator',
            description: "API client {$identifier} was deleted."
        );

        return $this->successResponse(
            message: 'API client deleted successfully.'
        );
    }

    /**
     * Derive a unique slug from the client name.
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'client';
        $slug = $base;
        $suffix = 2;

        while (ApiClient::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
