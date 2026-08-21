<?php

namespace App\Http\Resources\Api\Customer;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trimmed to what an autocomplete row needs — id, label, thumbnail, and the
 * category it lives under so the app can route straight into it. No description,
 * no pagination.
 */
class ServiceSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => Media::url($this->image),
            'category' => [
                'id' => $this->category_id,
                'name' => $this->category?->name,
            ],
        ];
    }
}
