<?php

namespace Modules\MBKM\Enums;

enum RequirementType: string
{
    case ACADEMIC = 'academic';
    case ADMINISTRATIVE = 'administrative';
    case DOCUMENT = 'document';
    case CUSTOM = 'custom';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::ACADEMIC => 'Persyaratan Akademik',
            self::ADMINISTRATIVE => 'Persyaratan Administratif',
            self::DOCUMENT => 'Persyaratan Dokumen',
            self::CUSTOM => 'Persyaratan Khusus',
        };
    }
}
