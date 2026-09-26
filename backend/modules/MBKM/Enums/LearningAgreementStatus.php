<?php

namespace Modules\MBKM\Enums;

enum LearningAgreementStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case LOCKED = 'locked';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Once approved/locked the agreement must not be edited through the normal
     * edit endpoints anymore; a formal revision flow is required instead.
     */
    public function isLocked(): bool
    {
        return in_array($this->value, [self::APPROVED->value, self::LOCKED->value], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Diajukan',
            self::REVIEWED => 'Ditinjau',
            self::APPROVED => 'Disetujui',
            self::LOCKED => 'Terkunci',
        };
    }
}
