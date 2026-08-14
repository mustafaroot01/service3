<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;

class NotificationService
{
    public function __construct(private readonly PushService $push)
    {
    }

    /**
     * The stored row is the record the recipient reads in the app, so it is
     * written first; the device push is a mirror of it.
     */
    public function send(?Model $notifiable, string $title, string $body, NotificationType $type, array $data = []): void
    {
        if (! $notifiable) {
            return;
        }

        Notification::create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data,
        ]);

        $this->push->send(
            $notifiable->getMorphClass().':'.$notifiable->getKey(),
            $title,
            $body,
            $data
        );
    }
}
