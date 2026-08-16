<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'governorate_id' => $this->governorate_id,
            'governorate' => $this->whenLoaded('governorate', fn () => [
                'id' => $this->governorate->id,
                'name' => $this->governorate->name,
            ]),
            'district_id' => $this->district_id,
            'district' => $this->whenLoaded('district', fn () => [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ]),
            'orders_count' => $this->whenCounted('orders'),
            'deletion_requested' => $this->deletion_requested_at !== null,
            'deletion_requested_at' => $this->deletion_requested_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
