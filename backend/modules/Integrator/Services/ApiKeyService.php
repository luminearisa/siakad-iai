<?php

namespace Modules\Integrator\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Audit\Services\AuditService;
use Modules\Identity\Models\User;
use Modules\Integrator\Enums\ApiKeyScope;
use Modules\Integrator\Models\ApiClient;
use Modules\Integrator\Models\ApiKey;
use Modules\Settings\Services\SettingService;

/**
 * Issues, rotates, revokes and verifies integration API keys.
 *
 * The plaintext token exists only in the return value of {@see generate()} and
 * {@see rotate()}. Everything else in the system works with the prefix + hash pair.
 */
class ApiKeyService
{
    public const TOKEN_PREFIX = 'sk';

    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * Create a new key for a client.
     *
     * @param  array{name: string, scopes: array<int, string>, expires_at?: string|null}  $attributes
     * @return array{key: ApiKey, plain: string}
     */
    public function generate(ApiClient $client, array $attributes, ?User $actor = null): array
    {
        $plain = $this->newPlainToken();

        $key = $client->keys()->create([
            'name' => $attributes['name'],
            'key_prefix' => $plain['prefix'],
            'key_hash' => $this->hash($plain['token']),
            'scopes' => array_values(array_unique($attributes['scopes'])),
            'expires_at' => $attributes['expires_at'] ?? null,
            'created_by' => $actor?->id,
        ]);

        AuditService::log(
            action: 'created',
            module: 'integrator',
            description: "API key {$key->key_prefix} was created for client {$client->slug}.",
            newValues: $this->auditValues($key),
            user: $actor
        );

        return ['key' => $key, 'plain' => $plain['token']];
    }

    /**
     * Replace a key with a fresh secret that keeps the same name, client and scopes.
     *
     * Rotation is implemented as revoke + create (never as an in-place overwrite) so
     * the old credential can be traced in the audit log and in `api_request_logs`.
     *
     * @return array{key: ApiKey, plain: string, revoked: ApiKey}
     */
    public function rotate(ApiKey $key, ?User $actor = null, ?string $reason = null): array
    {
        $revoked = $this->revoke($key, $reason ?? 'Rotated', $actor);

        $created = $this->generate($revoked->client, [
            'name' => $key->name,
            'scopes' => $key->scopeList(),
            'expires_at' => $key->expires_at?->toDateTimeString(),
        ], $actor);

        AuditService::log(
            action: 'rotated',
            module: 'integrator',
            description: "API key {$revoked->key_prefix} was rotated into {$created['key']->key_prefix}.",
            oldValues: ['key_prefix' => $revoked->key_prefix],
            newValues: $this->auditValues($created['key']),
            user: $actor
        );

        return [
            'key' => $created['key'],
            'plain' => $created['plain'],
            'revoked' => $revoked,
        ];
    }

    /**
     * Revoke a key. Revoking twice is a no-op so retries stay idempotent.
     */
    public function revoke(ApiKey $key, ?string $reason = null, ?User $actor = null): ApiKey
    {
        if ($key->isRevoked()) {
            return $key;
        }

        $key->forceFill([
            'revoked_at' => Carbon::now(),
            'revoked_reason' => $reason,
        ])->save();

        AuditService::log(
            action: 'revoked',
            module: 'integrator',
            description: "API key {$key->key_prefix} was revoked.",
            oldValues: ['revoked_at' => null],
            newValues: $this->auditValues($key->refresh()),
            user: $actor
        );

        return $key->refresh();
    }

    /**
     * Resolve a plaintext token into an API key.
     *
     * Fast path is the unique prefix index; the hash comparison afterwards is
     * constant-time, so an attacker cannot learn the secret from response timing.
     */
    public function resolve(?string $token): ?ApiKey
    {
        if (! is_string($token) || $token === '' || ! str_contains($token, '.')) {
            return null;
        }

        $prefix = Str::before($token, '.');

        $key = ApiKey::query()
            ->with('client')
            ->where('key_prefix', $prefix)
            ->first();

        if (! $key) {
            return null;
        }

        if (! hash_equals($key->key_hash, $this->hash($token))) {
            return null;
        }

        return $key;
    }

    /**
     * Record a successful authentication and usage of the key.
     *
     * The client row is only touched once per minute: it exists for dashboards, not
     * for request accounting, so per-request writes would be pure waste.
     */
    public function recordUsage(ApiKey $key, ?string $ip, ?ApiClient $client = null): void
    {
        $now = Carbon::now();

        ApiKey::withoutTimestamps(fn () => $key->newQuery()->whereKey($key->getKey())->update([
            'request_count' => $key->request_count + 1,
            'last_used_at' => $now,
            'last_used_ip' => $ip,
        ]));

        $client ??= $key->client;

        if ($client && (! $client->last_used_at || $client->last_used_at->diffInSeconds($now) >= 60)) {
            ApiClient::withoutTimestamps(fn () => $client->newQuery()->whereKey($client->getKey())->update([
                'last_used_at' => $now,
            ]));
        }
    }

    /**
     * All scopes available for assignment, as value => label pairs.
     *
     * @return array<string, string>
     */
    public function availableScopes(): array
    {
        return ApiKeyScope::labels();
    }

    /**
     * Default scopes offered when an operator creates a key for a data puller.
     *
     * @return array<int, string>
     */
    public function defaultScopes(): array
    {
        return [
            ApiKeyScope::REFERENCE_READ->value,
            ApiKeyScope::ACADEMIC_READ->value,
            ApiKeyScope::STUDENTS_READ->value,
            ApiKeyScope::LECTURERS_READ->value,
            ApiKeyScope::COURSES_READ->value,
            ApiKeyScope::CURRICULA_READ->value,
            ApiKeyScope::CLASSES_READ->value,
            ApiKeyScope::ENROLLMENTS_READ->value,
            ApiKeyScope::GRADES_READ->value,
            ApiKeyScope::ACTIVITIES_READ->value,
            ApiKeyScope::GRADUATION_READ->value,
        ];
    }

    /**
     * Default rate limit (requests per minute) for a new client.
     */
    public function defaultRateLimit(): int
    {
        $configured = $this->settingService->get('integrator.default_rate_limit', 120);

        return is_numeric($configured) ? max(1, (int) $configured) : 120;
    }

    /**
     * SHA-256 of the full token; the database never sees the token itself.
     */
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Fields that are safe to copy into `audit_logs`.
     *
     * `key_hash` is intentionally absent: the audit trail records *that* a credential
     * was issued or revoked, never the credential material itself.
     *
     * @return array<string, mixed>
     */
    protected function auditValues(ApiKey $key): array
    {
        return [
            'id' => $key->id,
            'api_client_id' => $key->api_client_id,
            'key_prefix' => $key->key_prefix,
            'name' => $key->name,
            'scopes' => $key->scopeList(),
            'expires_at' => $key->expires_at?->toIso8601String(),
            'revoked_at' => $key->revoked_at?->toIso8601String(),
        ];
    }

    /**
     * Build a fresh token: `<prefix>.<secret>`.
     *
     * @return array{prefix: string, token: string}
     */
    protected function newPlainToken(): array
    {
        $prefix = self::TOKEN_PREFIX.'_'.Str::lower(Str::random(12));
        $secret = Str::random(48);

        return [
            'prefix' => $prefix,
            'token' => $prefix.'.'.$secret,
        ];
    }
}
