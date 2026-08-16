<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Services\NotificationService;
use App\Support\OrderNotificationContent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendOrderStatusNotification implements ShouldQueue
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

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->loadMissing(['user', 'technician', 'district']);

        $payload = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'from_status' => $event->from?->value,
            'to_status' => $event->to->value,
        ];

        $this->notifications->send(
            $order->user,
            OrderNotificationContent::title($event->to),
            OrderNotificationContent::body($order, $event->to, $event->note),
            NotificationType::ORDER_STATUS,
            $payload
        );

        if ($event->to === OrderStatus::ASSIGNED && $order->technician) {
            $this->notifications->send(
                $order->technician,
                'طلب جديد مُسند إليك',
                OrderNotificationContent::technicianBody($order),
                NotificationType::ORDER_STATUS,
                $payload
            );
        }
    }
}
