<?php

namespace App\Http\Resources\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\MediaType;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'whatsapp' => Phone::whatsapp($this->phone, "بخصوص طلب انضمامك كفني — {$this->full_name}"),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_next_statuses' => collect($this->status->allowedNext())
                ->map(fn (ApplicationStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->all(),
            'note' => $this->note,
            'governorate_id' => $this->governorate_id,
            'governorate' => new GovernorateResource($this->whenLoaded('governorate')),
            'district_id' => $this->district_id,
            'district' => new DistrictResource($this->whenLoaded('district')),
            'specializations' => SpecializationResource::collection($this->whenLoaded('specializations')),
            'documents' => $this->whenLoaded('media', fn () => $this->documents()),
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function documents(): array
    {
        $documents = [];

        foreach (MediaType::cases() as $type) {
            $files = TechnicianApplicationMediaResource::collection($this->mediaOfType($type))->resolve();

            $documents[$type->value] = $type->holdsOneFile() ? ($files[0] ?? null) : $files;
        }

        return $documents;
    }
}
