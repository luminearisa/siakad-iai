<?php

namespace Modules\Curriculum\Enums;

enum CurriculumStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ARCHIVED = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
