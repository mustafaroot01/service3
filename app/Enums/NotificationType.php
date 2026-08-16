<?php

namespace App\Enums;

enum NotificationType: string
{
    case ORDER_STATUS = 'order_status';
    case TECHNICIAN_REASSIGNED = 'technician_reassigned';
    case BLOG_POST = 'blog_post';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_STATUS => 'تغيّر حالة الطلب',
            self::TECHNICIAN_REASSIGNED => 'تغيّر الفني المكلّف',
            self::BLOG_POST => 'مقال جديد',
        };
    }
}
