<?php

declare(strict_types=1);

namespace Integrator\Siakad;

use Integrator\Support\Http;
use RuntimeException;

/**
 * Client for the SIAKAD integration API (`/api/v1/integrator/v1/*`).
 *
 * Authenticates with the `X-API-Key` header issued from SIAKAD's Integrator module
 * and understands the platform's standard response envelope
 * (`{success, message, data, meta}`).
 */
final class SiakadClient
{
    public function __construct(
        private readonly Http $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly bool $verifySsl = true
    ) {
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function configured(): bool
    {
        return $this->baseUrl() !== '' && trim($this->apiKey) !== '';
    }

    /**
     * Health probe: proves the key works and lists its scopes.
     *
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        return $this->request('ping');
    }

    /**
     * Domain counters, used on the dashboard.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?int $semesterId = null): array
    {
        return $this->request('snapshot', $semesterId ? ['semester_id' => $semesterId] : []);
    }

    /**
     * Semester list including the PDDikti semester code (`20251`).
     *
     * @return array<int, array<string, mixed>>
     */
    public function semesters(): array
    {
        $data = $this->request('semesters');

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * One page of a paginated endpoint.
     *
     * @param  array<string, mixed>  $query
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listPage(string $endpoint, array $query = []): array
    {
        $response = $this->raw($endpoint, $query);

        $data = $response['data'] ?? [];
        $meta = $response['meta'] ?? [];

        return [
            'data' => is_array($data) ? array_values(array_filter($data, 'is_array')) : [],
            'meta' => is_array($meta) ? $meta : [],
        ];
    }

    /**
     * Stream every page of an endpoint into a callback.
     *
     * The callback receives one row plus the page number; returning `false`
     * stops the iteration (used for `--limit`).
     *
     * @param  array<string, mixed>  $query
     * @return array{rows: int, pages: int, stopped: bool}
     */
    public function each(string $endpoint, array $query, callable $callback, int $limit = 0): array
    {
        $page = 1;
        $rows = 0;
        $pages = 0;
        $stopped = false;

        // Pull in a stable order so a long sync cannot double-process rows.
        $query += ['sort' => 'id', 'direction' => 'asc'];

        while (true) {
            $query['page'] = $page;
            $result = $this->listPage($endpoint, $query);
            $pages++;

            if ($result['data'] === []) {
                break;
            }

            foreach ($result['data'] as $row) {
                $rows++;

                if ($callback($row, $page) === false) {
                    $stopped = true;
                    break 2;
                }

                if ($limit > 0 && $rows >= $limit) {
                    $stopped = true;
                    break 2;
                }
            }

            $lastPage = (int) ($result['meta']['last_page'] ?? 1);

            if ($page >= $lastPage) {
                break;
            }

            $page++;

            // Be a good citizen: no need to hammer an internal API.
            usleep(50_000);
        }

        return ['rows' => $rows, 'pages' => $pages, 'stopped' => $stopped];
    }

    /**
     * Request an endpoint and return its `data` payload.
     *
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>
     */
    public function request(string $endpoint, array $query = []): array
    {
        $response = $this->raw($endpoint, $query);
        $data = $response['data'] ?? [];

        if (! is_array($data)) {
            throw new SiakadException('Respons SIAKAD tidak berisi data yang dapat dibaca.', 0, null, ['endpoint' => $endpoint]);
        }

        return $data;
    }

    /**
     * Request an endpoint and return the full envelope.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function raw(string $endpoint, array $query = []): array
    {
        if (! $this->configured()) {
            throw new SiakadException('Base URL atau API key SIAKAD belum diisi. Buka halaman Pengaturan.');
        }

        $url = $this->baseUrl().'/api/v1/integrator/v1/'.ltrim($endpoint, '/');

        $response = $this->http
            ->withVerifySsl($this->verifySsl)
            ->get($url, $query, [
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ]);

        $payload = $response->json();

        if (! $response->ok()) {
            $message = (string) ($payload['message'] ?? $response->error ?? 'Permintaan ke SIAKAD gagal.');
            $errors = is_array($payload['errors'] ?? null) ? $payload['errors'] : [];

            throw new SiakadException($message, $response->status, null, $errors);
        }

        if (($payload['success'] ?? false) !== true) {
            throw new SiakadException((string) ($payload['message'] ?? 'SIAKAD menolak permintaan.'), $response->status);
        }

        return $payload;
    }
}

/**
 * Raised for every non-2xx answer or malformed envelope from SIAKAD.
 */
final class SiakadException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly int $status = 0,
        ?\Throwable $previous = null,
        private readonly array $context = []
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Operator-friendly hint for the most common failures.
     */
    public function hint(): string
    {
        return match (true) {
            $this->status === 401 => 'API key ditolak atau sudah dicabut. Terbitkan key baru di SIAKAD.',
            $this->status === 403 => 'API key tidak memiliki scope yang dibutuhkan endpoint ini.',
            $this->status === 422 => 'Parameter tidak valid (periksa semester atau filter yang dikirim).',
            $this->status === 429 => 'Rate limit SIAKAD tercapai. Kurangi jumlah permintaan per menit.',
            $this->status >= 500 => 'SIAKAD mengalami galat internal. Cek log aplikasi SIAKAD.',
            default => 'Periksa base URL, koneksi jaringan, dan konfigurasi API key.',
        };
    }
}
