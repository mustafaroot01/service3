<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status?->value,
            'from_status_label' => $this->from_status?->label(),
            'to_status' => $this->to_status->value,
            'to_status_label' => $this->to_status->label(),
            'actor_type' => $this->actor_type->value,
            'actor_type_label' => $this->actor_type->label(),
            'actor_id' => $this->actor_id,
            'actor_name' => $this->actorName(),
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
