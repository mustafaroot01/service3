<?php

namespace App\Http\Resources\Api\Customer;

use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderTechnicianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'whatsapp' => Phone::whatsapp($this->phone),
        ];
    }
}
