<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;

class OrderNotificationContent
{
    public static function title(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::PENDING => 'تم استلام طلبك',
            OrderStatus::CONFIRMED => 'تم تأكيد طلبك',
            OrderStatus::ASSIGNED => 'تم تعيين فني لطلبك',
            OrderStatus::INSPECTED => 'تم الكشف على طلبك',
            OrderStatus::COMPLETED => 'تم إنجاز طلبك',
            OrderStatus::CANCELLED => 'تم إلغاء طلبك',
        };
    }

    public static function body(Order $order, OrderStatus $status, ?string $note = null): string
    {
        $number = $order->order_number;

        return match ($status) {
            OrderStatus::PENDING => "استلمنا طلبك رقم {$number} وسنتواصل معك قريباً",
            OrderStatus::CONFIRMED => "طلبك رقم {$number} مؤكّد، سنعيّن فنياً قريباً",
            OrderStatus::ASSIGNED => $order->technician
                ? "الفني {$order->technician->name} سيتولى طلبك رقم {$number}"
                : "تم تعيين فني لطلبك رقم {$number}",
            OrderStatus::INSPECTED => $note
                ? "نتيجة الكشف على طلبك رقم {$number}: {$note}"
                : "تم الكشف على طلبك رقم {$number}",
            OrderStatus::COMPLETED => "تم إنجاز الخدمة لطلبك رقم {$number}، شكراً لثقتك",
            OrderStatus::CANCELLED => "تم إلغاء طلبك رقم {$number}",
        };
    }

    public static function technicianBody(Order $order): string
    {
        return "تم تعيينك لطلب رقم {$order->order_number} في {$order->district?->name}";
    }
}
