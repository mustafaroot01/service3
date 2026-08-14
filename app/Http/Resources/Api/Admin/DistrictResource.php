<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistrictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'governorate_id' => $this->governorate_id,
            'governorate' => new GovernorateResource($this->whenLoaded('governorate')),
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'users_count' => $this->whenCounted('users'),
            'technicians_count' => $this->whenCounted('technicians'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
