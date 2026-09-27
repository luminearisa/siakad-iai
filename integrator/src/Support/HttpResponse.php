<?php

declare(strict_types=1);

namespace Integrator\Support;

/**
 * Immutable HTTP result with helpers for JSON APIs.
 */
final class HttpResponse
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
        public readonly ?string $error = null
    ) {
    }

    public function ok(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300;
    }

    /**
     * Decode the body as JSON.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Short, loggable description of the exchange (never contains credentials).
     */
    public function summary(int $limit = 300): string
    {
        if ($this->error !== null) {
            return 'transport error: '.$this->error;
        }

        return 'HTTP '.$this->status.' '.mb_substr(trim(preg_replace('/\s+/', ' ', $this->body) ?? ''), 0, $limit);
    }
}
