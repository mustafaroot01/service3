<?php

namespace App\Http\Resources\Api\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        [$moduleKey, $actionKey] = array_pad(explode('.', $this->name, 2), 2, null);

        $module = PermissionModule::tryFrom((string) $moduleKey);
        $action = PermissionAction::tryFrom((string) $actionKey);

        return [
            'id' => $this->id,
            'key' => $this->name,
            'module' => $moduleKey,
            'module_label' => $module?->label() ?? $moduleKey,
            'group' => $module?->group(),
            'action' => $actionKey,
            'action_label' => $action?->label() ?? $actionKey,
            'label' => trim(($action?->label() ?? $actionKey).' — '.($module?->label() ?? $moduleKey)),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'label' => $role->label ?: $role->name,
                    'is_locked' => $role->name === \App\Services\RoleService::LOCKED_ROLE,
                ])
                ->values()
                ->all()),
        ];
    }
}
