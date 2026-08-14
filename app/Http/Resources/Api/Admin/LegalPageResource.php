<?php

namespace App\Http\Resources\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key->value,
            'key_label' => $this->key->label(),
            'title' => $this->title,
            'content' => $this->content,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
