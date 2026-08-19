<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    public function __construct(private readonly PushService $push) {}

    /**
     * The stored row is the record the recipient reads in the app, so it is
     * written first; the device push is a mirror of it. Runs inside the request
     * now (no queue worker on the host), so the whole thing is wrapped — a
     * notification must never break the action that triggered it.
     */
    public function send(?Model $notifiable, string $title, string $body, NotificationType $type, array $data = []): void
    {
        if (! $notifiable) {
            return;
        }

        try {
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
        } catch (Throwable $e) {
            Log::warning('Notification delivery failed', [
                'to' => $notifiable->getMorphClass().':'.$notifiable->getKey(),
                'type' => $type->value,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
