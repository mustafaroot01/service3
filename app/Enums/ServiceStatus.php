<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'مفعّل',
            self::INACTIVE => 'غير مفعّل',
        };
    }
}
