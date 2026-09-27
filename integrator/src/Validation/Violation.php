<?php

declare(strict_types=1);

namespace Integrator\Validation;

/**
 * Satu temuan hasil validasi pra-kirim.
 *
 * Temuan bisa menempel pada satu baris data (`local_key` terisi) atau pada
 * tingkat entity (`entity_level = true`, misalnya catatan pemetaan default).
 */
final class Violation
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const NOTE = 'note';

    public function __construct(
        public readonly string $entity,
        public readonly string $code,
        public readonly string $severity,
        public readonly string $message,
        public readonly ?string $localKey = null,
        public readonly ?string $field = null,
        public readonly ?string $hint = null,
        public readonly ?string $prodi = null,
        public readonly ?string $period = null,
        public readonly bool $entityLevel = false
    ) {}

    public function isError(): bool
    {
        return $this->severity === self::ERROR;
    }

    public function isNote(): bool
    {
        return $this->entityLevel || $this->severity === self::NOTE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'local_key' => $this->localKey ?? '-',
            'field' => $this->field,
            'code' => $this->code,
            'severity' => $this->severity,
            'message' => $this->message,
            'hint' => $this->hint,
            'prodi' => $this->prodi,
            'period' => $this->period,
            'entity_level' => $this->entityLevel,
        ];
    }

    /**
     * Pesan ringkas untuk log sinkronisasi.
     */
    public function logMessage(): string
    {
        $label = $this->field !== null ? $this->field.' — ' : '';

        return 'Validasi gagal: '.$label.$this->message.($this->hint !== null ? ' ('.$this->hint.')' : '');
    }
}
