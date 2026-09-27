<?php

declare(strict_types=1);

namespace Integrator\Reporting;

/**
 * Hasil satu kali pemeriksaan validasi + cakupan, dipakai CLI dan halaman web.
 */
final class ValidationSummary
{
    /**
     * @param  array<string, array<string, mixed>>  $entities
     * @param  array<string, int|float>  $totals
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly string $runId,
        public readonly string $scope,
        public readonly ?string $semester,
        public readonly array $entities,
        public readonly array $totals,
        public readonly array $errors = [],
        public readonly float $durationSeconds = 0.0,
        public readonly bool $stored = true
    ) {}

    public function ok(): bool
    {
        return (int) ($this->totals['invalid'] ?? 0) === 0 && $this->errors === [];
    }

    public function invalid(): int
    {
        return (int) ($this->totals['invalid'] ?? 0);
    }

    public function percentage(): float
    {
        return round((float) ($this->totals['percentage'] ?? 0), 2);
    }

    public function summary(): string
    {
        $parts = [
            'data '.number_format((int) ($this->totals['total'] ?? 0), 0, ',', '.').' baris',
            'valid '.number_format((int) ($this->totals['valid'] ?? 0), 0, ',', '.'),
            'tidak valid '.number_format((int) ($this->totals['invalid'] ?? 0), 0, ',', '.'),
            'sudah tersinkron '.number_format((int) ($this->totals['synced'] ?? 0), 0, ',', '.'),
            'cakupan '.number_format($this->percentage(), 2, ',', '.').'%',
        ];

        return implode(', ', $parts);
    }

    /**
     * Baris tabel untuk CLI/console.
     *
     * @return array<int, array<int, string>>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->entities as $entity => $counts) {
            $rows[] = [
                (string) $entity,
                number_format((int) ($counts['total'] ?? 0), 0, ',', '.'),
                number_format((int) ($counts['valid'] ?? 0), 0, ',', '.'),
                number_format((int) ($counts['invalid'] ?? 0), 0, ',', '.'),
                number_format((int) ($counts['synced'] ?? 0), 0, ',', '.'),
                number_format((float) ($counts['percentage'] ?? 0), 2, ',', '.').'%',
            ];
        }

        return $rows;
    }
}
