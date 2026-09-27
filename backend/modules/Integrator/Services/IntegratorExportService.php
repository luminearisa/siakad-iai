<?php

namespace Modules\Integrator\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Integrator\Models\ApiClient;
use Modules\Integrator\Models\ApiKey;
use Modules\Integrator\Models\ApiRequestLog;

/**
 * Menyiapkan data yang berkaitan dengan pelaporan PDDikti / Neo Feeder agar dapat
 * diunduh operator ("Export as…") sebagai CSV (dibuka di Excel) atau JSON (arsip
 * mesin / diserahkan ke tim integrator).
 *
 * Setiap dataset memakai bentuk yang sama:
 *
 *     ['headers' => string[], 'rows' => iterable<int, array<int, mixed>>]
 *
 * sehingga controller cukup menyalurkannya ke berkas unduhan.
 *
 * Catatan aman:
 *  - rahasia TIDAK pernah ikut: kunci API hanya diwakili `key_prefix`, sedangkan
 *    hash token tidak pernah disimpan dalam bentuk terbaca;
 *  - jumlah baris dibatasi {@see self::MAX_ROWS} supaya satu unduhan tidak
 *    menghabiskan memori server; log lama bisa dipersempit lewat filter tanggal.
 */
final class IntegratorExportService
{
    /**
     * Batas baris per berkas unduhan.
     */
    public const MAX_ROWS = 50000;

    /**
     * Log permintaan API: bukti "siapa menarik data apa, kapan, dan berhasil/tidak".
     *
     * Filter yang dipahami sama dengan endpoint `/integrator/logs`: `api_client_id`,
     * `api_key_id`, `method`, `status_code`, `successful`, `from`, `to`, `search`.
     *
     * @param  array<string, mixed>  $filters
     * @return array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function requestLogs(array $filters = [], int $limit = self::MAX_ROWS): array
    {
        $query = ApiRequestLog::query()
            ->with(['client:id,name,slug', 'key:id,name,key_prefix'])
            ->orderBy('created_at');

        $this->applyLogFilters($query, $filters);

        $headers = [
            'waktu',
            'klien',
            'slug_klien',
            'kunci',
            'metode',
            'endpoint',
            'parameter',
            'status_http',
            'berhasil',
            'durasi_ms',
            'ip',
            'user_agent',
            'pesan_galat',
        ];

        $cap = $this->clampLimit($limit);

        $rows = (function () use ($query, $cap) {
            $sent = 0;

            foreach ($query->lazy(500) as $log) {
                if ($sent >= $cap) {
                    break;
                }

                $sent++;

                yield [
                    $this->moment($log->created_at),
                    $log->client?->name,
                    $log->client?->slug,
                    $log->key?->key_prefix,
                    strtoupper((string) $log->method),
                    $log->path,
                    $log->query,
                    (int) $log->status_code,
                    $log->isSuccessful() ? 'ya' : 'tidak',
                    (int) $log->duration_ms,
                    $log->ip_address,
                    $log->user_agent,
                    $log->error_message,
                ];
            }
        })();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Rekap harian per klien: dipakai untuk lampiran laporan pelaporan PDDikti
     * (jumlah permintaan, tingkat keberhasilan, galat 4xx/5xx, rata-rata durasi).
     *
     * Dihitung di PHP agar hasilnya sama di SQLite/MySQL/PostgreSQL.
     *
     * @param  array<string, mixed>  $filters
     * @return array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function requestSummary(array $filters = [], int $limit = self::MAX_ROWS): array
    {
        $query = ApiRequestLog::query()
            ->with('client:id,name,slug')
            ->orderBy('created_at');

        $this->applyLogFilters($query, $filters);

        $headers = [
            'tanggal',
            'klien',
            'slug_klien',
            'jumlah_permintaan',
            'sukses_2xx',
            'galat_klien_4xx',
            'galat_server_5xx',
            'rata_durasi_ms',
            'permintaan_terakhir',
        ];

        $cap = $this->clampLimit($limit);

        $rows = (function () use ($query, $cap) {
            /** @var array<string, array<string, mixed>> $buckets */
            $buckets = [];
            $seen = 0;

            foreach ($query->lazy(1000) as $log) {
                if ($seen >= $cap) {
                    break;
                }

                $seen++;

                $date = $log->created_at?->format('Y-m-d') ?? '—';
                $client = $log->client?->name ?? '(tanpa klien)';
                $bucketKey = $date.'|'.$client;

                if (! isset($buckets[$bucketKey])) {
                    $buckets[$bucketKey] = [
                        'tanggal' => $date,
                        'klien' => $client,
                        'slug' => $log->client?->slug,
                        'total' => 0,
                        'sukses' => 0,
                        'galat_klien' => 0,
                        'galat_server' => 0,
                        'durasi' => 0,
                        'terakhir' => null,
                    ];
                }

                $status = (int) $log->status_code;

                $buckets[$bucketKey]['total']++;
                $buckets[$bucketKey]['durasi'] += (int) $log->duration_ms;
                $buckets[$bucketKey]['sukses'] += ($status >= 200 && $status <= 299) ? 1 : 0;
                $buckets[$bucketKey]['galat_klien'] += ($status >= 400 && $status <= 499) ? 1 : 0;
                $buckets[$bucketKey]['galat_server'] += $status >= 500 ? 1 : 0;
                $buckets[$bucketKey]['terakhir'] = $this->moment($log->created_at);
            }

            ksort($buckets);

            foreach ($buckets as $bucket) {
                yield [
                    $bucket['tanggal'],
                    $bucket['klien'],
                    $bucket['slug'],
                    $bucket['total'],
                    $bucket['sukses'],
                    $bucket['galat_klien'],
                    $bucket['galat_server'],
                    $bucket['total'] > 0 ? round($bucket['durasi'] / $bucket['total'], 2) : 0,
                    $bucket['terakhir'],
                ];
            }
        })();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Daftar klien integrasi beserta ringkasan kuncinya.
     *
     * @return array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function clients(): array
    {
        $headers = [
            'id',
            'nama',
            'slug',
            'deskripsi',
            'email_kontak',
            'aktif',
            'ip_diizinkan',
            'batas_per_menit',
            'jumlah_kunci',
            'kunci_aktif',
            'terakhir_dipakai',
            'dibuat',
        ];

        $rows = (function () {
            $clients = ApiClient::query()
                ->withCount(['keys', 'activeKeys'])
                ->orderBy('name')
                ->cursor();

            foreach ($clients as $client) {
                yield [
                    (int) $client->id,
                    $client->name,
                    $client->slug,
                    $client->description,
                    $client->contact_email,
                    $client->is_active ? 'ya' : 'tidak',
                    implode(', ', $client->allowed_ips ?? []),
                    (int) $client->rate_limit_per_minute,
                    (int) $client->keys_count,
                    (int) $client->active_keys_count,
                    $this->moment($client->last_used_at),
                    $this->moment($client->created_at),
                ];
            }
        })();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Daftar kunci API (prefix + scope + masa berlaku), tanpa rahasia apa pun.
     *
     * @param  array<string, mixed>  $filters  mendukung `api_client_id` dan `status`
     *                                          (`active`, `revoked`, `expired`).
     * @return array{headers: array<int, string>, rows: iterable<int, array<int, mixed>>}
     */
    public function keys(array $filters = []): array
    {
        $query = ApiKey::query()
            ->with('client:id,name,slug')
            ->orderBy('api_client_id')
            ->orderBy('id');

        if (! empty($filters['api_client_id'])) {
            $query->where('api_client_id', $filters['api_client_id']);
        }

        $this->applyKeyStatusFilter($query, $filters['status'] ?? null);

        $headers = [
            'id',
            'klien',
            'slug_klien',
            'nama_kunci',
            'prefix',
            'scope',
            'status',
            'kedaluwarsa',
            'dicabut',
            'alasan_pencabutan',
            'terakhir_dipakai',
            'ip_terakhir',
            'jumlah_permintaan',
            'dibuat',
        ];

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $key) {
                yield [
                    (int) $key->id,
                    $key->client?->name,
                    $key->client?->slug,
                    $key->name,
                    $key->key_prefix,
                    implode(', ', $key->scopeList()),
                    $key->status()->value,
                    $this->moment($key->expires_at),
                    $this->moment($key->revoked_at),
                    $key->revoked_reason,
                    $this->moment($key->last_used_at),
                    $key->last_used_ip,
                    (int) $key->request_count,
                    $this->moment($key->created_at),
                ];
            }
        })();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Metadata yang ikut ditulis pada berkas JSON (dan dicatat pada log aplikasi).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function meta(string $dataset, array $filters): array
    {
        return [
            'dataset' => $dataset,
            'source' => 'SIAKAD → Integrator PDDikti / Neo Feeder',
            'generated_at' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'row_limit' => self::MAX_ROWS,
            'filters' => array_filter($filters, static fn ($value) => $value !== null && $value !== ''),
        ];
    }

    /**
     * Samakan semantik filter dengan endpoint `/integrator/logs` agar isi unduhan
     * persis sama dengan yang dilihat operator di layar.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyLogFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('successful', $filters) && $filters['successful'] !== null && $filters['successful'] !== '') {
            filter_var($filters['successful'], FILTER_VALIDATE_BOOLEAN)
                ? $query->whereBetween('status_code', [200, 299])
                : $query->whereNotBetween('status_code', [200, 299]);
        }

        foreach (['api_client_id', 'api_key_id', 'status_code'] as $column) {
            if (! empty($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (! empty($filters['method'])) {
            $query->where('method', strtoupper((string) $filters['method']));
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search']) && is_string($filters['search'])) {
            $term = '%'.$filters['search'].'%';

            $query->where(function (Builder $inner) use ($term) {
                $inner->where('path', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('error_message', 'like', $term)
                    ->orWhere('user_agent', 'like', $term);
            });
        }
    }

    private function applyKeyStatusFilter(Builder $query, mixed $status): void
    {
        match ($status) {
            'active' => $query->whereNull('revoked_at')
                ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            'revoked' => $query->whereNotNull('revoked_at'),
            'expired' => $query->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()),
            default => null,
        };
    }

    private function clampLimit(int $limit): int
    {
        return max(1, min($limit, self::MAX_ROWS));
    }

    /**
     * Waktu lokal yang enak dibaca di Excel (kolom JSON tetap ISO 8601 di meta).
     */
    private function moment(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : null;
    }
}
