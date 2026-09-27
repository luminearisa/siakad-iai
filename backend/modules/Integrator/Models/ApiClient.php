<?php

namespace Modules\Integrator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Audit\Traits\Auditable;
use Modules\Identity\Models\User;

/**
 * A system that is allowed to pull data from SIAKAD (for example the standalone
 * `integrator/` application that bridges SIAKAD to Neo Feeder PDDikti).
 */
class ApiClient extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'contact_email',
        'is_active',
        'allowed_ips',
        'rate_limit_per_minute',
        'last_used_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allowed_ips' => 'array',
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Keys belonging to this client.
     */
    public function keys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * Keys of this client that are neither revoked nor expired.
     */
    public function activeKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Request log rows produced by this client.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class);
    }

    /**
     * Staff user that registered the client.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the client may authenticate from the given IP address.
     *
     * An empty allow-list means "any IP". Both plain addresses and CIDR ranges
     * (`10.0.0.0/8`, `2001:db8::/32`) are supported.
     */
    public function allowsIp(string $ip): bool
    {
        $rules = $this->allowed_ips;

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            $rule = trim((string) $rule);

            if ($rule === '') {
                continue;
            }

            if ($rule === $ip) {
                return true;
            }

            if (str_contains($rule, '/') && $this->ipInCidr($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Native CIDR match that works for both IPv4 and IPv6 without extra extensions.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);

        if ($bits === null || ! is_numeric($bits)) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainder)) - 1) & 0xFF;

        return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
    }
}
