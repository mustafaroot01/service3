<?php

namespace App\Http\Resources\Api\Admin;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianApplicationMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'url' => Media::secureUrl($this->path),
            'sort' => $this->sort,
        ];
    }
}
