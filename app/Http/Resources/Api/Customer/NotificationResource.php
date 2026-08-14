<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type->value,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'order_id' => $this->data['order_id'] ?? null,
            'order_number' => $this->data['order_number'] ?? null,
            'status' => $this->data['to_status'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
