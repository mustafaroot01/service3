<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'content' => $this->content,
            'published_at' => $this->published_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'notified_at' => $this->notified_at?->toIso8601String(),
            'is_announced' => $this->notified_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
