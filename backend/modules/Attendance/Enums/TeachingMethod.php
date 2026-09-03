<?php

namespace Modules\Attendance\Enums;

enum TeachingMethod: string
{
    case OFFLINE = 'offline';   // Tatap Muka
    case ONLINE = 'online';     // Daring / Kuliah Online
    case HYBRID = 'hybrid';     // Hybrid / Bauran

    public function label(): string
    {
        return match ($this) {
            self::OFFLINE => 'Tatap Muka (Luring)',
            self::ONLINE => 'Daring (Online)',
            self::HYBRID => 'Hybrid (Bauran)',
        };
    }
}
