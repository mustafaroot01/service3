<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_final' => $this->status->isFinal(),
            'can_cancel' => $this->status->isCancellableByCustomer(),
            'description' => $this->description,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'time_from' => $this->time_from?->format('H:i'),
            'time_to' => $this->time_to?->format('H:i'),
            'visit_ends_next_day' => $this->visitEndsNextDay(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'landmark' => $this->landmark,
            'inspection_note' => $this->inspection_note,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'governorate' => new GovernorateResource($this->whenLoaded('governorate')),
            'district' => new DistrictResource($this->whenLoaded('district')),
            'technician' => $this->when(
                $this->relationLoaded('technician') && $this->technician !== null,
                fn () => (new OrderTechnicianResource($this->technician))->resolve()
            ),
            'images' => OrderImageResource::collection($this->whenLoaded('images')),
            'timeline' => OrderTimelineResource::collection($this->whenLoaded('statusHistories')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
