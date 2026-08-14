<?php

namespace App\Enums;

enum AdminStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::SUSPENDED => 'موقوف',
        };
    }
}
