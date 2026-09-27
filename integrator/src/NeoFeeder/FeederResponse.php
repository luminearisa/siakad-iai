<?php

declare(strict_types=1);

namespace Integrator\NeoFeeder;

use Integrator\Support\HttpResponse;

/**
 * One Neo Feeder answer, normalised.
 *
 * Two different things can go wrong and they must stay distinguishable:
 * - transport/HTTP failure (`error`), e.g. feeder down or wrong URL;
 * - PDDikti validation failure (`error_code != 0`), e.g. "NIK sudah digunakan".
 */
final class FeederResponse
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        private readonly string $act,
        private readonly int $status,
        private readonly array $payload,
        private readonly ?string $error
    ) {
    }

    public static function fromHttp(string $act, HttpResponse $http): self
    {
        return new self($act, $http->status, $http->json(), $http->error);
    }

    public function act(): string
    {
        return $this->act;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): int
    {
        return (int) ($this->payload['error_code'] ?? ($this->error === null ? -1 : -1));
    }

    public function errorDesc(): string
    {
        if ($this->error !== null) {
            return $this->error;
        }

        return (string) ($this->payload['error_desc'] ?? '');
    }

    public function isOk(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300 && $this->errorCode() === 0;
    }

    /**
     * Transport-level failure (feeder unreachable, TLS refused, timeout), if any.
     */
    public function transportError(): ?string
    {
        return $this->error;
    }

    /**
     * Operator-facing message: PDDikti's own wording when available.
     */
    public function message(): string
    {
        if ($this->error !== null) {
            return 'Koneksi ke Neo Feeder gagal: '.$this->error;
        }

        if ($this->status < 200 || $this->status >= 300) {
            return 'Neo Feeder menjawab HTTP '.$this->status.'.';
        }

        $description = trim($this->errorDesc());

        return $description !== ''
            ? $description
            : 'Neo Feeder menolak permintaan (error_code '.$this->errorCode().').';
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function data(): mixed
    {
        return $this->payload['data'] ?? null;
    }

    /**
     * `data` as a list of rows, accepting both list and single-object shapes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $data = $this->data();

        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        // A single associative row, or a wrapper like {"data": {"id_mahasiswa": ...}}.
        return [$data];
    }

    /**
     * Feeder id of a freshly inserted row, if the endpoint returned one.
     */
    public function createdId(): ?string
    {
        foreach ($this->rows() as $row) {
            foreach (['id_mahasiswa', 'id_dosen', 'id_kelas', 'id_matkul', 'id_kurikulum', 'id_registrasi_mahasiswa', 'id_aktivitas', 'id_semester'] as $key) {
                if (! empty($row[$key])) {
                    return (string) $row[$key];
                }
            }
        }

        $direct = $this->data();

        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        return null;
    }

    /**
     * Compact, credential-free description for the sync log.
     */
    public function summary(int $limit = 400): string
    {
        if ($this->error !== null) {
            return 'transport: '.$this->error;
        }

        $encoded = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        return mb_substr($encoded, 0, $limit);
    }
}
