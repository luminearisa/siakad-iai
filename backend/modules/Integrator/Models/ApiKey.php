<?php

namespace Modules\Integrator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;
use Modules\Integrator\Enums\ApiKeyStatus;
use Modules\Integrator\Enums\ApiKeyScope;

/**
 * A bearer credential owned by an {@see ApiClient}.
 *
 * The plaintext token is generated once, returned once and never stored: only a
 * SHA-256 hash plus a short public prefix live in the database. Lookups go through
 * the prefix, verification goes through `hash_equals`, so a leaked database row
 * cannot be replayed as a working key.
 *
 * Note: this model deliberately does **not** use the `Auditable` trait. The trait
 * writes every attribute to `audit_logs`, which would copy `key_hash` (credential
 * material) into a second table. Lifecycle events are logged explicitly by
 * {@see \Modules\Integrator\Services\ApiKeyService} with sanitised values instead.
 */
class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_client_id',
        'name',
        'key_prefix',
        'key_hash',
        'scopes',
        'expires_at',
        'revoked_at',
        'revoked_reason',
        'last_used_at',
        'last_used_ip',
        'request_count',
        'created_by',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
            'request_count' => 'integer',
        ];
    }

    /**
     * Owning client.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    /**
     * Requests made with this key.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /**
     * Staff user that created the key.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the key may currently authenticate a request.
     */
    public function isUsable(): bool
    {
        return $this->status()->isUsable();
    }

    /**
     * Derived lifecycle status.
     */
    public function status(): ApiKeyStatus
    {
        return ApiKeyStatus::for($this);
    }

    /**
     * Granted scopes as raw values.
     *
     * @return array<int, string>
     */
    public function scopeList(): array
    {
        return array_values(array_unique($this->scopes ?? []));
    }

    /**
     * Whether the key holds the given scope.
     *
     * Accepts a single scope or a list; a list is satisfied by **any** match so it
     * can be used directly from route middleware (`api.key:grades.read|activities.read`).
     *
     * @param  string|array<int, string>|ApiKeyScope  $scopes
     */
    public function can(string|array|ApiKeyScope $scopes): bool
    {
        $required = match (true) {
            $scopes instanceof ApiKeyScope => [$scopes->value],
            is_array($scopes) => $scopes,
            default => preg_split('/[|,]/', $scopes) ?: [],
        };

        $required = array_values(array_filter(array_map('trim', $required)));

        if ($required === []) {
            return false;
        }

        $granted = $this->scopeList();

        foreach ($required as $scope) {
            if (in_array($scope, $granted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scopes with their human readable labels, for the management UI.
     *
     * @return array<int, array{value: string, label: string, sensitive: bool}>
     */
    public function scopeDetails(): array
    {
        $labels = ApiKeyScope::labels();
        $sensitive = ApiKeyScope::sensitive();

        return array_map(
            fn (string $scope) => [
                'value' => $scope,
                'label' => $labels[$scope] ?? $scope,
                'sensitive' => in_array($scope, $sensitive, true),
            ],
            $this->scopeList()
        );
    }
}
