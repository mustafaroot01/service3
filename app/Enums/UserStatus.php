<?php

namespace App\Enums;

enum UserStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case SCHEDULED_FOR_DELETION = 'scheduled_for_deletion';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'بانتظار التوثيق',
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::SUSPENDED => 'موقوف',
            self::SCHEDULED_FOR_DELETION => 'مجدول للحذف',
        };
    }
}
