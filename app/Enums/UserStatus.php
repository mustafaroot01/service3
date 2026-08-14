<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case PENDING = 'pending';
    case SCHEDULED_FOR_DELETION = 'scheduled_for_deletion';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::SUSPENDED => 'موقوف',
            self::PENDING => 'قيد الانتظار',
            self::SCHEDULED_FOR_DELETION => 'مجدول للحذف',
        };
    }
}
