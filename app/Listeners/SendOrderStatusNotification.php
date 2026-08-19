<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Services\NotificationService;
use App\Support\OrderNotificationContent;

/**
 * Runs synchronously (no ShouldQueue): the host has no queue worker, so a
 * queued notification would sit in the jobs table forever. NotificationService
 * swallows its own failures, so a push hiccup never breaks the status change
 * that triggered it.
 */
class SendOrderStatusNotification
{
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
