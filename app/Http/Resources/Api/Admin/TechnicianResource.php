<?php

namespace App\Http\Resources\Api\Admin;

use App\Enums\MediaType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'governorate_id' => $this->governorate_id,
            'governorate' => new GovernorateResource($this->whenLoaded('governorate')),
            'district_id' => $this->district_id,
            'district' => new DistrictResource($this->whenLoaded('district')),
            'specializations' => SpecializationResource::collection($this->whenLoaded('specializations')),
            'orders_count' => $this->whenCounted('orders'),
            'documents' => $this->whenLoaded('media', fn () => $this->documents()),
            'documents_complete' => $this->whenLoaded('media', fn () => $this->hasCompleteDocuments()),
            'missing_documents' => $this->whenLoaded('media', fn () => collect($this->missingDocuments())
                ->map(fn (MediaType $type) => ['type' => $type->value, 'label' => $type->label()])
                ->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function documents(): array
    {
        $documents = [];

        foreach (MediaType::cases() as $type) {
            $files = TechnicianMediaResource::collection($this->mediaOfType($type))->resolve();

            $documents[$type->value] = $type->holdsOneFile() ? ($files[0] ?? null) : $files;
        }

        return $documents;
    }
}
