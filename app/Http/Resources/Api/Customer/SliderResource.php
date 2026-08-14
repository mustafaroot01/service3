<?php

namespace App\Http\Resources\Api\Customer;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SliderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image' => Media::url($this->image),
            'link' => $this->link,
        ];
    }
}
