<?php

namespace App\Http\Resources\Api\Customer;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'image' => Media::url($this->image),
            'images' => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => Media::url($img->path),
            ])->values()->all(),
            'description' => $this->description,
        ];
    }
}
