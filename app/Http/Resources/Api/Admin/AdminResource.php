<?php

namespace App\Http\Resources\Api\Admin;

use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

class AdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->whenLoaded('roles', fn () => $this->roles->first());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'role_id' => $role instanceof Role ? $role->id : null,
            'role' => $role instanceof Role
                ? ['id' => $role->id, 'label' => $role->label ?: $role->name, 'is_locked' => $role->name === RoleService::LOCKED_ROLE]
                : null,
            'is_self' => $request->user('admin')?->getKey() === $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
