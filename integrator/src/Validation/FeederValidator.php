<?php

declare(strict_types=1);

namespace Integrator\Validation;

use Integrator\Sync\ArrayPath;
use Integrator\Sync\ReferenceResolver;

/**
 * Validasi baris hasil pemetaan sebelum dikirim ke Neo Feeder.
 *
 * Aturan hidup di `config/feeder_rules.php` (data, bukan kode) sementara mesin
 * ini menyediakan tipe aturan yang dipakai Neo Feeder: format NIK/NISN, nomor HP
 * 08…, email, tanggal, rentang nilai, keberadaan data pada tabel referensi, dan
 * konsistensi antar kolom (komposisi SKS, jumlah mahasiswa vs kapasitas).
 *
 * Aturan yang bergantung pada data + aturan yang belum terpenuhi karena entitas
 * induk belum tersinkron dibedakan: yang pertama `error` (feeder pasti menolak),
 * yang kedua `warning` (baris tinggal menunggu sinkronisasi entitas induk).
 */
final class FeederValidator
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $referenceCache = [];

    /**
     * @param  array<string, mixed>  $rules  isi config/feeder_rules.php
     * @param  array<string, mixed>  $mapping  isi config/feeder_mapping.php
     */
    public function __construct(
        private readonly array $rules,
        private readonly array $mapping,
        private readonly ReferenceResolver $references
    ) {}

    public static function load(string $rulesPath, array $mapping, ReferenceResolver $references): self
    {
        $rules = require $rulesPath;

        return new self(is_array($rules) ? $rules : [], $mapping, $references);
    }

    public function minimumFeederVersion(): string
    {
        return (string) ($this->rules['minimum_feeder_version'] ?? '3.0.1');
    }

    /**
     * Apakah versi feeder yang terdeteksi/tercatat masih memenuhi minimum yang
     * divalidasi aplikasi ini? Versi yang tidak diketahui dianggap belum memenuhi.
     */
    public function versionCompatible(?string $version): bool
    {
        if ($version === null || trim($version) === '') {
            return false;
        }

        return version_compare($this->normaliseVersion($version), $this->normaliseVersion($this->minimumFeederVersion()), '>=');
    }

    private function normaliseVersion(string $version): string
    {
        preg_match('/\d+(?:\.\d+)*/', $version, $matches);

        return $matches[0] ?? '0';
    }

    public function minimumFeederVersionNote(): ?string
    {
        $note = $this->rules['minimum_feeder_version_note'] ?? null;

        return is_string($note) ? $note : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function entityConfigs(): array
    {
        $entities = $this->rules['entities'] ?? [];

        return is_array($entities) ? $entities : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function entityConfig(string $entity): array
    {
        $config = $this->entityConfigs()[$entity] ?? [];

        return is_array($config) ? $config : [];
    }

    public function labelFor(string $entity): string
    {
        $label = $this->entityConfig($entity)['label'] ?? null;

        if (is_string($label) && $label !== '') {
            return $label;
        }

        $mapped = $this->mapping['entities'][$entity]['label'] ?? null;

        return is_string($mapped) && $mapped !== '' ? $mapped : $entity;
    }

    public function hasRules(string $entity): bool
    {
        return $this->entityConfig($entity) !== [];
    }

    /**
     * Validasi satu baris hasil pemetaan.
     *
     * @param  array<string, mixed>  $payload  hasil FieldMapper (nama kolom feeder)
     * @param  array<string, mixed>  $source  baris asli dari SIAKAD
     * @return array<int, Violation>
     */
    public function validateRow(string $entity, string $localKey, array $payload, array $source, ?string $period = null): array
    {
        $config = $this->entityConfig($entity);

        if ($config === []) {
            return [];
        }

        $prodi = $this->prodiFor($entity, $payload, $source);
        $prodiKey = $prodi['key'] ?? null;
        $view = array_merge($source, $payload);
        $violations = [];

        // Kolom yang diterima dalam bentuk tertutup (masked) tidak bisa dilaporkan:
        // nilainya sengaja tidak dibaca API karena kunci tidak memegang scope PII.
        $masked = $this->maskedFields($payload);

        if ($masked !== []) {
            $violations[] = new Violation(
                entity: $entity,
                code: 'data_tertutup_pii',
                severity: Violation::ERROR,
                message: 'Kolom '.implode(', ', $masked).' diterima dalam bentuk tertutup (masked) sehingga PDDikti akan menolaknya.',
                localKey: $localKey,
                field: implode(', ', $masked),
                hint: 'API key SIAKAD belum memegang scope students.pii. Tambahkan scope tersebut pada kunci di SIAKAD, lalu jalankan ulang pemeriksaan — data tertutup hanya untuk tampilan, bukan untuk pelaporan.',
                prodi: $prodiKey,
                period: $period
            );
        }

        foreach ($config['rules'] ?? [] as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            if (isset($rule['field']) && is_string($rule['field']) && in_array($rule['field'], $masked, true)) {
                continue;
            }

            $violation = $this->evaluate($entity, $localKey, $rule, $view, $prodiKey, $period);

            if ($violation !== null) {
                $violations[] = $violation;
            }
        }

        foreach ($this->dependencyViolations($entity, $localKey, $payload, $config, $prodiKey, $period) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * Catatan kesiapan data tingkat entity (dicek sekali, bukan per baris).
     *
     * @return array<int, Violation>
     */
    public function notes(string $entity, ?string $period = null, ?string $prodi = null): array
    {
        $notes = $this->entityConfig($entity)['notes'] ?? [];
        $violations = [];

        if (! is_array($notes)) {
            return [];
        }

        foreach ($notes as $code => $message) {
            if (! is_string($message)) {
                continue;
            }

            $violations[] = new Violation(
                entity: $entity,
                code: 'catatan_'.(string) $code,
                severity: Violation::NOTE,
                message: $message,
                prodi: $prodi,
                period: $period,
                entityLevel: true
            );
        }

        return $violations;
    }

    /**
     * Program studi baris ini: id feeder (bila sudah dipetakan) atau kode prodi
     * dari SIAKAD, lengkap dengan label yang bisa dibaca operator.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $source
     * @return array{key: string, label: string}|null
     */
    public function prodiFor(string $entity, array $payload, array $source): ?array
    {
        $idProdi = ArrayPath::get($payload, 'id_prodi');

        if ($this->filled($idProdi)) {
            foreach ($this->referenceRows('prodi') as $row) {
                if ((string) ($row['feeder_id'] ?? '') === (string) $idProdi) {
                    return ['key' => (string) $idProdi, 'label' => $this->prodiLabel($row)];
                }
            }

            return ['key' => (string) $idProdi, 'label' => 'Prodi '.$idProdi];
        }

        $sourceField = $this->entityConfig($entity)['prodi_source_field'] ?? null;

        if (! is_string($sourceField) || $sourceField === '') {
            return null;
        }

        $code = ArrayPath::get($source, $sourceField);

        if (! $this->filled($code)) {
            return null;
        }

        $row = $this->references->find('prodi', (string) $code);

        return [
            'key' => (string) $code,
            'label' => $row !== null ? $this->prodiLabel($row) : 'Prodi '.$code,
        ];
    }

    // -----------------------------------------------------------------
    // Mesin aturan
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $view
     */
    private function evaluate(
        string $entity,
        string $localKey,
        array $rule,
        array $view,
        ?string $prodi,
        ?string $period
    ): ?Violation {
        // Nama aturan dipisah dari argumennya: `digits:16` → `digits` + `16`.
        $ruleName = explode(':', (string) ($rule['rule'] ?? ''), 2)[0];
        $field = isset($rule['field']) ? (string) $rule['field'] : null;
        $code = (string) ($rule['code'] ?? ($field !== null ? $field.'_'.$ruleName : $ruleName));
        $severity = (string) ($rule['severity'] ?? Violation::ERROR);

        if ($ruleName === '') {
            return null;
        }

        if (isset($rule['when']) && is_array($rule['when']) && ! $this->whenMatches($rule['when'], $view)) {
            return null;
        }

        $failed = false;

        if ($ruleName === 'sum_equals') {
            $failed = ! $this->sumEquals($rule, $view);
        } else {
            $value = $field !== null ? ArrayPath::get($view, $field) : null;
            $failed = ! $this->passes($ruleName, $rule, $value, $view);
        }

        if (! $failed) {
            return null;
        }

        return new Violation(
            entity: $entity,
            code: $code,
            severity: $severity,
            message: (string) ($rule['message'] ?? 'Nilai tidak memenuhi aturan Neo Feeder.'),
            localKey: $localKey,
            field: $field ?? (isset($rule['target']) ? (string) $rule['target'] : null),
            hint: isset($rule['hint']) ? (string) $rule['hint'] : null,
            prodi: $prodi,
            period: $period
        );
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $view
     */
    private function passes(string $ruleName, array $rule, mixed $value, array $view): bool
    {
        $argument = isset($rule['rule']) ? explode(':', (string) $rule['rule'], 2)[1] ?? null : null;

        if ($ruleName === 'required') {
            return $this->filled($value);
        }

        if ($ruleName === 'empty') {
            return ! $this->filled($value);
        }

        // Selain `required` dan `empty`, kolom kosong dianggap tidak melanggar:
        // pengosongan yang dilarang sudah ditangkap aturan `required`.
        if (! $this->filled($value)) {
            return true;
        }

        $string = is_scalar($value) ? trim((string) $value) : '';

        return match ($ruleName) {
            'digits' => ctype_digit($string) && strlen($string) === (int) $argument,
            'numeric' => is_numeric($string),
            'integer' => is_numeric($string) && (float) $string === floor((float) $string),
            'email' => filter_var($string, FILTER_VALIDATE_EMAIL) !== false,
            'phone_id' => preg_match('/^08[0-9]{7,13}$/', preg_replace('/[^0-9]/', '', $string) ?? '') === 1,
            'date' => $this->isDate($string),
            'not_future' => $this->isDate($string) && strtotime($string) <= strtotime('today'),
            'in' => in_array($string, array_map('trim', explode(',', (string) $argument)), true),
            'min' => is_numeric($string) && (float) $string >= (float) $argument,
            'max' => is_numeric($string) && (float) $string <= (float) $argument,
            'maxlen' => mb_strlen($string) <= (int) $argument,
            'exists' => $this->existsInReference((string) $argument, $string),
            'lte' => $this->compareWith($string, ArrayPath::get($view, (string) $argument), 'lte'),
            'after_or_equal' => $this->compareWith($string, ArrayPath::get($view, (string) $argument), 'after_or_equal'),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $when
     * @param  array<string, mixed>  $view
     */
    private function whenMatches(array $when, array $view): bool
    {
        $field = (string) ($when['field'] ?? '');

        if ($field === '') {
            return true;
        }

        $value = ArrayPath::get($view, $field);
        $rule = (string) ($when['rule'] ?? 'filled');

        return match ($rule) {
            'filled' => $this->filled($value),
            'empty' => ! $this->filled($value),
            'in' => in_array((string) $value, array_map('strval', (array) ($when['values'] ?? [])), true),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $view
     */
    private function sumEquals(array $rule, array $view): bool
    {
        $target = ArrayPath::get($view, (string) ($rule['target'] ?? ''));
        $parts = [];
        $anyFilled = false;

        foreach ((array) ($rule['parts'] ?? []) as $part) {
            $value = ArrayPath::get($view, (string) $part);

            if ($this->filled($value)) {
                $anyFilled = true;
                $parts[] = (float) $value;

                continue;
            }

            $parts[] = 0.0;
        }

        if (! $anyFilled || ! $this->filled($target)) {
            return true;
        }

        return abs(array_sum($parts) - (float) $target) < 0.0001;
    }

    private function compareWith(string $value, mixed $other, string $mode): bool
    {
        if (! $this->filled($other)) {
            return true;
        }

        if ($mode === 'lte') {
            return is_numeric($value) && is_numeric((string) $other) && (float) $value <= (float) $other;
        }

        $left = strtotime($value);
        $right = strtotime(is_scalar($other) ? (string) $other : '');

        if ($left === false || $right === false) {
            return true;
        }

        return $left >= $right;
    }

    /**
     * Kolom yang nilainya ter-mask (mis. 3201**********12) — penanda bahwa kunci
     * API tidak memegang scope PII, bukan tanda data kosong di SIAKAD.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function maskedFields(array $payload): array
    {
        $fields = [];

        foreach ($payload as $field => $value) {
            if (is_string($value) && preg_match('/\*{2,}/', $value) === 1) {
                $fields[] = (string) $field;
            }
        }

        return $fields;
    }

    /**
     * Kolom yang wajib diisi tapi kosong bukan "error data", melainkan
     * ketergantungan: entitas induk belum tersinkron atau referensi belum ditarik.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<int, Violation>
     */
    private function dependencyViolations(
        string $entity,
        string $localKey,
        array $payload,
        array $config,
        ?string $prodi,
        ?string $period
    ): array {
        $fields = $this->mapping['entities'][$entity]['fields'] ?? [];

        if (! is_array($fields)) {
            return [];
        }

        $covered = [];

        foreach ($config['rules'] ?? [] as $rule) {
            if (is_array($rule) && isset($rule['field']) && ($rule['rule'] ?? '') === 'required') {
                $covered[(string) $rule['field']] = true;
            }
        }

        $violations = [];

        foreach ($fields as $target => $spec) {
            if (! is_string($spec) || isset($covered[(string) $target]) || $this->filled(ArrayPath::get($payload, (string) $target))) {
                continue;
            }

            if (str_starts_with($spec, '@mapping:')) {
                $parent = explode(':', $spec)[1] ?? '';

                if ($parent === $entity) {
                    // Id milik entity ini sendiri: belum tersinkron, bukan error data.
                    continue;
                }

                $violations[] = new Violation(
                    entity: $entity,
                    code: 'menunggu_'.($parent !== '' ? $parent : 'entitas_induk'),
                    severity: Violation::WARNING,
                    message: 'Kolom '.(string) $target.' menunggu sinkronisasi entitas '.(string) ($parent !== '' ? $parent : 'induk').'.',
                    localKey: $localKey,
                    field: (string) $target,
                    hint: 'Jalankan sinkronisasi entitas induk lebih dulu, lalu ulangi baris ini.',
                    prodi: $prodi,
                    period: $period
                );

                continue;
            }

            if (str_starts_with($spec, '@ref:')) {
                $kind = explode(':', $spec)[1] ?? 'referensi';

                $violations[] = new Violation(
                    entity: $entity,
                    code: 'referensi_'.$kind.'_kosong',
                    severity: Violation::WARNING,
                    message: 'Kolom '.(string) $target.' belum bisa diisi karena referensi '.$kind.' tidak memuat data ini.',
                    localKey: $localKey,
                    field: (string) $target,
                    hint: 'Tarik referensi Neo Feeder pada halaman Referensi dan pastikan kodenya sama dengan PDDikti.',
                    prodi: $prodi,
                    period: $period
                );
            }
        }

        return $violations;
    }

    private function existsInReference(string $kind, string $value): bool
    {
        if ($this->references->find($kind, $value) !== null) {
            return true;
        }

        foreach ($this->referenceRows($kind) as $row) {
            if ((string) ($row['feeder_id'] ?? '') === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function referenceRows(string $kind): array
    {
        return $this->referenceCache[$kind] ??= $this->references->all($kind, 2000);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function prodiLabel(array $row): string
    {
        $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];

        foreach (['nama_program_studi', 'nama_prodi', 'nama'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && (string) $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        return (string) ($row['reference_key'] ?? '-');
    }

    private function isDate(string $value): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    private function filled(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }
}
