<?php

namespace App\Http\Resources\Api\Customer;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image' => Media::url($this->image),
            'content' => $this->content,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
