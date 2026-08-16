<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type->value,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'order_id' => $data['order_id'] ?? null,
            'order_number' => $data['order_number'] ?? null,
            'status' => $data['to_status'] ?? null,
            'blog_post_id' => $data['blog_post_id'] ?? null,
            // The whole stored payload, identical to the one the OneSignal push
            // carries, so the app deep-links the same way whether it opened the
            // push or read the list later — and a new notification type never
            // needs this resource touched again to reach the client.
            'data' => $data,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
