<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderTimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->to_status->value,
            'status_label' => $this->to_status->label(),
            'note' => $this->note,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
