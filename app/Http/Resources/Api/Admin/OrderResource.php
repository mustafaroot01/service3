<?php

namespace App\Http\Resources\Api\Admin;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Support\Phone;
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
            'allowed_next_statuses' => collect($this->status->allowedNext())
                ->map(fn (OrderStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
            'description' => $this->description,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'time_from' => $this->time_from?->format('H:i'),
            'time_to' => $this->time_to?->format('H:i'),
            'visit_ends_next_day' => $this->visitEndsNextDay(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'landmark' => $this->landmark,
            'map_url' => "https://maps.google.com/?q={$this->latitude},{$this->longitude}",
            'inspection_note' => $this->inspection_note,
            'cancelled_by' => $this->cancelled_by,
            'cancelled_by_label' => $this->cancelled_by ? ActorType::tryFrom($this->cancelled_by)?->label() : null,
            'cancellation_note' => $this->cancellationNote(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'service_id' => $this->service_id,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'technician_id' => $this->technician_id,
            'technician' => new TechnicianResource($this->whenLoaded('technician')),
            'governorate_id' => $this->governorate_id,
            'governorate' => new GovernorateResource($this->whenLoaded('governorate')),
            'district_id' => $this->district_id,
            'district' => new DistrictResource($this->whenLoaded('district')),
            'images' => OrderImageResource::collection($this->whenLoaded('images')),
            'status_histories' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistories')),
            'whatsapp' => [
                'customer' => $this->relationLoaded('user')
                    ? Phone::whatsapp($this->user?->phone, $this->contactMessage())
                    : null,
                'technician' => $this->relationLoaded('technician')
                    ? Phone::whatsapp($this->technician?->phone, $this->contactMessage())
                    : null,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** The reason the admin typed when cancelling lives in the audit trail. */
    private function cancellationNote(): ?string
    {
        if (! $this->relationLoaded('statusHistories')) {
            return null;
        }

        return $this->statusHistories
            ->firstWhere('to_status', OrderStatus::CANCELLED)?->note;
    }

    private function contactMessage(): string
    {
        return "بخصوص طلب الخدمة رقم {$this->order_number}";
    }
}
