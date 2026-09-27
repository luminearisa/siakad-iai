<?php

namespace Modules\Integrator\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Integrator\Enums\ApiKeyScope;
use Modules\Integrator\Enums\ApiKeyStatus;
use Modules\Integrator\Models\ApiClient;
use Modules\Integrator\Models\ApiKey;
use Modules\Integrator\Requests\RevokeApiKeyRequest;
use Modules\Integrator\Requests\RotateApiKeyRequest;
use Modules\Integrator\Requests\StoreApiKeyRequest;
use Modules\Integrator\Resources\ApiKeyResource;
use Modules\Integrator\Services\ApiKeyService;

/**
 * Staff-facing management of integration API keys.
 *
 * The plaintext token is composed of a public prefix and a secret; only the prefix
 * and a SHA-256 hash are stored. Consequently `store()` and `rotate()` are the only
 * responses in the whole system that contain a usable credential — after that the
 * operator must rotate the key to get a new one.
 */
class ApiKeyController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected ApiKeyService $apiKeyService
    ) {}

    /**
     * Available scopes plus the defaults offered by the UI.
     */
    public function scopes(): JsonResponse
    {
        return $this->successResponse(
            data: [
                'scopes' => collect(ApiKeyScope::cases())->map(fn (ApiKeyScope $scope) => [
                    'value' => $scope->value,
                    'label' => $scope->label(),
                    'sensitive' => in_array($scope->value, ApiKeyScope::sensitive(), true),
                ])->values(),
                'groups' => ApiKeyScope::groups(),
                'defaults' => $this->apiKeyService->defaultScopes(),
                'statuses' => ApiKeyStatus::labels(),
            ],
            message: 'API key scopes retrieved successfully.'
        );
    }

    /**
     * Paginated key list across all clients.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', Rule::in(ApiKeyStatus::values())],
        ]);

        $query = ApiKey::query()
            ->with('client:id,name,slug,is_active')
            ->withCount('logs');

        if ($status = $request->query('status')) {
            $this->applyStatusFilter($query, $status);
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'key_prefix'],
            filterableColumns: ['api_client_id'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'API keys retrieved successfully.',
            resourceClass: ApiKeyResource::class
        );
    }

    /**
     * Issue a new key for a client. The plaintext token is returned exactly once.
     */
    public function store(StoreApiKeyRequest $request, ApiClient $client): JsonResponse
    {
        $result = $this->apiKeyService->generate($client, [
            'name' => $request->validated('name'),
            'scopes' => $request->scopes(),
            'expires_at' => $request->expiresAt(),
        ], $request->user());

        return $this->successResponse(
            data: [
                'key' => new ApiKeyResource($result['key']->load('client')),
                'plain_key' => $result['plain'],
                'warning' => 'Simpan token ini sekarang. Untuk alasan keamanan token tidak dapat ditampilkan lagi.',
                'usage' => [
                    'header' => 'X-API-Key: '.$result['plain'],
                    'example' => rtrim((string) config('app.url'), '/').'/api/v1/integrator/v1/ping',
                ],
            ],
            message: 'API key created successfully.',
            code: 201
        );
    }

    /**
     * Rotate a key: the old secret is revoked, a new one is issued with the same scopes.
     */
    public function rotate(RotateApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        $result = $this->apiKeyService->rotate(
            key: $key,
            actor: $request->user(),
            reason: $request->reason() ?? 'Rotated by staff'
        );

        // Rotation with an explicit expiry replaces the inherited one.
        if ($expiresAt = $request->expiresAt()) {
            $result['key']->forceFill(['expires_at' => $expiresAt])->save();
        }

        return $this->successResponse(
            data: [
                'key' => new ApiKeyResource($result['key']->refresh()->load('client')),
                'revoked_key' => new ApiKeyResource($result['revoked']),
                'plain_key' => $result['plain'],
                'warning' => 'Simpan token ini sekarang. Untuk alasan keamanan token tidak dapat ditampilkan lagi.',
            ],
            message: 'API key rotated successfully.',
            code: 201
        );
    }

    /**
     * Revoke a key. Idempotent: revoking an already revoked key returns 200.
     */
    public function revoke(RevokeApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        $wasRevoked = $key->isRevoked();

        $key = $this->apiKeyService->revoke(
            key: $key,
            reason: $request->reason() ?? 'Revoked by staff',
            actor: $request->user()
        );

        return $this->successResponse(
            data: new ApiKeyResource($key->load('client')),
            message: $wasRevoked
                ? 'API key was already revoked.'
                : 'API key revoked successfully.'
        );
    }

    /**
     * Delete a revoked key. Live keys must be revoked first so the request log
     * keeps pointing at a key that explains what happened.
     */
    public function destroy(ApiKey $key): JsonResponse
    {
        if (! $key->isRevoked()) {
            return $this->errorResponse(
                message: 'Hanya API key yang sudah dicabut yang dapat dihapus.',
                code: 422
            );
        }

        DB::transaction(function () use ($key) {
            $key->logs()->update(['api_key_id' => null]);
            $key->delete();
        });

        return $this->successResponse(
            message: 'API key deleted successfully.'
        );
    }

    /**
     * Apply the derived-status filter to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ApiKey>  $query
     */
    protected function applyStatusFilter($query, string $status): void
    {
        match ($status) {
            ApiKeyStatus::REVOKED->value => $query->whereNotNull('revoked_at'),
            ApiKeyStatus::EXPIRED->value => $query
                ->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()),
            ApiKeyStatus::CLIENT_INACTIVE->value => $query
                ->whereNull('revoked_at')
                ->whereHas('client', fn ($client) => $client->where('is_active', false)),
            default => $query
                ->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->whereHas('client', fn ($client) => $client->where('is_active', true)),
        };
    }
}
