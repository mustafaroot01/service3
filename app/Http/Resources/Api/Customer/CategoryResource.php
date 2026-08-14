<?php

namespace App\Http\Resources\Api\Customer;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => Media::url($this->image),
            'services_count' => $this->whenCounted('services'),
        ];
    }
}
