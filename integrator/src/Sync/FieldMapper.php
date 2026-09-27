<?php

declare(strict_types=1);

namespace Integrator\Sync;

use RuntimeException;

/**
 * Turns one SIAKAD row into a Neo Feeder payload using `config/feeder_mapping.php`.
 *
 * Supported value specs inside an entity's `fields` map:
 *
 * | Spec                       | Meaning                                                     |
 * |----------------------------|-------------------------------------------------------------|
 * | `some.dotted.path`         | value copied from the SIAKAD row                            |
 * | `@ref:prodi:{code}`        | feeder id looked up in the reference table (`GetAllProdi`…) |
 * | `@semester` / `@semester:{x}` | feeder id_semester from the run options or from the row  |
 * | `@mapping:classes:class:{class_code}` | feeder id stored for another entity's row         |
 * | `@literal:1`               | constant                                                    |
 * | `@int:x`, `@float:x`       | numeric cast                                                |
 * | `@bool:x`                  | `1`/`0`                                                     |
 * | `@date:x`, `@datetime:x`   | normalised date string                                      |
 */
final class FieldMapper
{
    /**
     * @param  array<string, mixed>  $mapping
     */
    public function __construct(private readonly array $mapping)
    {
    }

    public static function load(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("File mapping feeder tidak ditemukan: {$path}");
        }

        $config = require $path;

        if (! is_array($config)) {
            throw new RuntimeException('Isi config/feeder_mapping.php harus mengembalikan array.');
        }

        return new self($config);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function entities(): array
    {
        $entities = $this->mapping['entities'] ?? [];

        return is_array($entities) ? $entities : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entity(string $key): ?array
    {
        $config = $this->entities()[$key] ?? null;

        return is_array($config) ? $config : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function referenceConfig(): array
    {
        $config = $this->mapping['reference'] ?? [];

        return is_array($config) ? $config : [];
    }

    /**
     * Build the payload for one row.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function build(array $config, array $row, SyncContext $context, bool $isUpdate = false): array
    {
        $payload = [];

        foreach (($config['defaults'] ?? []) as $key => $value) {
            $payload[$key] = $value;
        }

        foreach (($config['fields'] ?? []) as $key => $spec) {
            $value = $this->resolve((string) $spec, $row, $context, $isUpdate);

            if ($value === null || $value === '') {
                // Never send empty strings: PDDikti rejects them and the row is
                // better skipped via `required` than pushed half-empty.
                unset($payload[$key]);

                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function missingRequired(array $config, array $payload): array
    {
        $missing = [];

        foreach (($config['required'] ?? []) as $field) {
            if (! array_key_exists((string) $field, $payload) || $payload[(string) $field] === '') {
                $missing[] = (string) $field;
            }
        }

        return $missing;
    }

    /**
     * Human readable field map for the UI (`kolom feeder → sumber SIAKAD`).
     *
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, string>>
     */
    public function preview(array $config): array
    {
        $rows = [];

        foreach (($config['fields'] ?? []) as $field => $spec) {
            $rows[] = [
                'field' => (string) $field,
                'source' => (string) $spec,
                'required' => in_array((string) $field, $config['required'] ?? [], true) ? 'ya' : '',
            ];
        }

        foreach (($config['defaults'] ?? []) as $field => $value) {
            $rows[] = [
                'field' => (string) $field,
                'source' => '@literal:'.$value,
                'required' => '',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolve(string $spec, array $row, SyncContext $context, bool $isUpdate): mixed
    {
        if (str_starts_with($spec, '@literal:')) {
            return substr($spec, 9);
        }

        if (str_starts_with($spec, '@ref:')) {
            $remainder = substr($spec, 5);
            [$kind, $template] = array_pad(explode(':', $remainder, 2), 2, '');

            return $context->feederId($kind, Template::render($template, $row));
        }

        if ($spec === '@semester' || str_starts_with($spec, '@semester:')) {
            $template = str_contains($spec, ':') ? substr($spec, 10) : null;

            return $context->semesterId($template === null || $template === '' ? null : Template::render($template, $row));
        }

        if (str_starts_with($spec, '@mapping:')) {
            $remainder = substr($spec, 9);
            [$entity, $template] = array_pad(explode(':', $remainder, 2), 2, '');
            $localKey = Template::render($template, $row);

            if ($localKey === '') {
                return null;
            }

            return $context->mapping($entity, $localKey)['feeder_id'] ?? null;
        }

        if (str_starts_with($spec, '@int:')) {
            $value = ArrayPath::get($row, substr($spec, 5));

            return is_numeric($value) ? (int) $value : null;
        }

        if (str_starts_with($spec, '@float:')) {
            $value = ArrayPath::get($row, substr($spec, 7));

            return is_numeric($value) ? (float) $value : null;
        }

        if (str_starts_with($spec, '@bool:')) {
            $value = ArrayPath::get($row, substr($spec, 6));

            if ($value === null) {
                return null;
            }

            return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        if (str_starts_with($spec, '@date:')) {
            return $this->normaliseDate((string) ArrayPath::get($row, substr($spec, 6)), 'Y-m-d');
        }

        if (str_starts_with($spec, '@datetime:')) {
            return $this->normaliseDate((string) ArrayPath::get($row, substr($spec, 10)), 'Y-m-d H:i:s');
        }

        $value = ArrayPath::get($row, $spec);

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return null;
        }

        return is_scalar($value) ? $value : null;
    }

    private function normaliseDate(?string $value, string $format): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return date($format, $timestamp);
    }
}
