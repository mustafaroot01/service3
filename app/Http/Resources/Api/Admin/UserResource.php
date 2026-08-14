<?php

namespace App\Http\Resources\Api\Admin;

use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'phone_verified' => $this->phone_verified_at !== null,
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'terms_accepted_at' => $this->terms_accepted_at?->toIso8601String(),
            'governorate_id' => $this->governorate_id,
            'governorate' => new GovernorateResource($this->whenLoaded('governorate')),
            'district_id' => $this->district_id,
            'district' => new DistrictResource($this->whenLoaded('district')),
            'orders_count' => $this->whenCounted('orders'),
            'whatsapp' => Phone::whatsapp($this->phone),
            'deletion_requested' => $this->deletion_requested_at !== null,
            'deletion_requested_at' => $this->deletion_requested_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
