<?php

namespace App\Http\Resources\Api\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key->value,
            'title' => $this->title,
            'content' => $this->content,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
