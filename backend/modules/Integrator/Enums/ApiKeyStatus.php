<?php

namespace Modules\Integrator\Enums;

use Modules\Integrator\Models\ApiKey;

/**
 * Derived lifecycle status of an API key.
 *
 * The status is never stored: it is always computed from the key and its client so
 * that "expired" and "client deactivated" can never drift out of sync with reality.
 */
enum ApiKeyStatus: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
    case CLIENT_INACTIVE = 'client_inactive';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve the status of a key (and optionally its client).
     */
    public static function for(ApiKey $key): self
    {
        if ($key->isRevoked()) {
            return self::REVOKED;
        }

        if ($key->isExpired()) {
            return self::EXPIRED;
        }

        if ($key->client && ! $key->client->is_active) {
            return self::CLIENT_INACTIVE;
        }

        return self::ACTIVE;
    }

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::REVOKED => 'Dicabut',
            self::EXPIRED => 'Kedaluwarsa',
            self::CLIENT_INACTIVE => 'Klien Nonaktif',
        };
    }

    /**
     * Whether a key in this state may authenticate a request.
     */
    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
