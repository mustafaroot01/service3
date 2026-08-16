<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\OrderTechnicianReassigned;
use App\Services\NotificationService;
use App\Support\OrderNotificationContent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendTechnicianReassignedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The order or technician this notification is about may be gone by the
     * time the worker picks the job up. There is nothing left to announce, so
     * the job is dropped instead of failing three times and being kept forever.
     */
    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    public array $backoff = [10, 60];

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
