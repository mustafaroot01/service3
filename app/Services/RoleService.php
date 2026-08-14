<?php

namespace App\Services;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public const GUARD = 'admin';

    /** Always holds every permission — editing it would be a way to lock everyone out. */
    public const LOCKED_ROLE = 'super-admin';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $roles = Role::where('guard_name', self::GUARD)
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get();

        $total = Permission::where('guard_name', self::GUARD)->count();
        $adminCounts = $this->adminCounts();

        return $roles->map(fn (Role $role) => $this->present($role, $total, (int) ($adminCounts[$role->id] ?? 0)))->all();
    }

    public function find(Role $role): array
    {
        return $this->present(
            $role->load('permissions:id,name'),
            Permission::where('guard_name', self::GUARD)->count(),
            (int) ($this->adminCounts()[$role->id] ?? 0)
        );
    }

    /**
     * @param  array{label: string, permissions: array<int, string>}  $data
     */
    public function create(array $data): array
    {
        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $this->identifierFor($data['label']),
                'label' => $data['label'],
                'guard_name' => self::GUARD,
            ]);

            $role->syncPermissions($this->validPermissions($data['permissions']));

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->find($role->refresh());
    }

    /**
     * @param  array{label: string, permissions: array<int, string>}  $data
     */
    public function update(Role $role, array $data): array
    {
        if ($role->name === self::LOCKED_ROLE) {
            throw ValidationException::withMessages([
                'permissions' => 'دور المدير العام يملك كل الصلاحيات ولا يمكن تعديله',
            ]);
        }

        DB::transaction(function () use ($role, $data) {
            $role->update(['label' => $data['label']]);
            $role->syncPermissions($this->validPermissions($data['permissions']));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->find($role->refresh());
    }

    public function delete(Role $role): void
    {
        if ($role->name === self::LOCKED_ROLE) {
            throw ValidationException::withMessages([
                'role' => 'دور المدير العام لا يُحذف',
            ]);
        }

        $assigned = (int) ($this->adminCounts()[$role->id] ?? 0);

        // Deleting the role would leave those admins with no permissions at all
        // and no way back into the panel.
        if ($assigned > 0) {
            throw ValidationException::withMessages([
                'role' => "الدور مُسند إلى {$assigned} مشرف، انقلهم إلى دور آخر قبل الحذف",
            ]);
        }

        DB::transaction(function () use ($role) {
            $role->syncPermissions([]);
            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The full grid the panel draws: one row per module, one column per action.
     *
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $existing = Permission::where('guard_name', self::GUARD)->pluck('name')->flip();

        $modules = collect(PermissionModule::cases())
            ->map(fn (PermissionModule $module) => [
                'key' => $module->value,
                'label' => $module->label(),
                'group' => $module->group(),
                'actions' => collect(PermissionAction::cases())
                    ->filter(fn (PermissionAction $action) => $existing->has("{$module->value}.{$action->value}"))
                    ->map(fn (PermissionAction $action) => $action->value)
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $module) => $module['actions'] !== [])
            ->values()
            ->all();

        return [
            'actions' => collect(PermissionAction::cases())
                ->map(fn (PermissionAction $action) => ['key' => $action->value, 'label' => $action->label()])
                ->all(),
            'modules' => $modules,
        ];
    }

    private function present(Role $role, int $total, int $adminsCount): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->label ?: $role->name,
            'is_locked' => $role->name === self::LOCKED_ROLE,
            'permissions_count' => $role->permissions->count(),
            'permissions_total' => $total,
            'admins_count' => $adminsCount,
            'permissions' => $role->permissions->pluck('name')->values()->all(),
        ];
    }

    /**
     * Spatie's users() relation reads guard_name off the model instance, which
     * withCount() cannot supply — it would silently count zero. One grouped
     * query over the pivot gives the real numbers.
     */
    private function adminCounts(): \Illuminate\Support\Collection
    {
        return DB::table('model_has_roles')
            ->where('model_type', (new Admin)->getMorphClass())
            ->selectRaw('role_id, count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function validPermissions(array $permissions): array
    {
        $known = Permission::where('guard_name', self::GUARD)->pluck('name')->all();
        $unknown = array_values(array_diff($permissions, $known));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'صلاحيات غير معروفة: '.implode('، ', $unknown),
            ]);
        }

        return $permissions;
    }

    /** Arabic does not slug, so the code identifier is generated and never typed. */
    private function identifierFor(string $label): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($label)) ?? '', '-');
        $base = $base !== '' ? $base : 'role';

        $name = $base;
        $suffix = 2;

        while (Role::where('name', $name)->where('guard_name', self::GUARD)->exists()) {
            $name = "{$base}-{$suffix}";
            $suffix++;
        }

        return $name;
    }
}
