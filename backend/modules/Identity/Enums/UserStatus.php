<?php

namespace Modules\Identity\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
