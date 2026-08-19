<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\OrderTechnicianReassigned;
use App\Services\NotificationService;
use App\Support\OrderNotificationContent;

/**
 * Runs synchronously (no ShouldQueue): the host has no queue worker.
 * NotificationService swallows its own failures, so a push hiccup never breaks
 * the reassignment that triggered it.
 */
class SendTechnicianReassignedNotification
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(OrderTechnicianReassigned $event): void
    {
        $order = $event->order->loadMissing(['user', 'district']);
        $number = $order->order_number;

        $data = [
            'order_id' => $order->id,
            'order_number' => $number,
            'to_status' => $order->status->value,
            'technician_id' => $event->current->id,
            'previous_technician_id' => $event->previous->id,
        ];

        $this->notifications->send(
            $order->user,
            'تم تغيير الفني المكلّف بطلبك',
            "الفني {$event->current->name} سيتولى طلبك رقم {$number} بدلاً من {$event->previous->name}",
            NotificationType::TECHNICIAN_REASSIGNED,
            $data
        );

        $this->notifications->send(
            $event->current,
            'طلب جديد مُسند إليك',
            OrderNotificationContent::technicianBody($order),
            NotificationType::TECHNICIAN_REASSIGNED,
            $data
        );

        // Without this the replaced technician still believes the job is his.
        $this->notifications->send(
            $event->previous,
            'تم سحب طلب منك',
            "لم يعد الطلب رقم {$number} مُسنداً إليك",
            NotificationType::TECHNICIAN_REASSIGNED,
            $data
        );
    }
}
