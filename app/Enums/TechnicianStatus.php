<?php

namespace App\Enums;

enum TechnicianStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case PENDING = 'pending';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::SUSPENDED => 'موقوف',
            self::PENDING => 'قيد الانتظار',
        };
    }
}
