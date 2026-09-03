<?php

namespace Modules\Assessment\Enums;

enum SchemeStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Aktif',
            self::ARCHIVED => 'Diarsipkan',
        };
    }
}
