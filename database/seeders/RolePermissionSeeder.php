<?php

namespace Database\Seeders;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public const GUARD = 'admin';

    /** The catalogue lives in the enums so the panel and the seeder cannot drift. */
    public static function modules(): array
    {
        return PermissionModule::values();
    }

    public static function actions(): array
    {
        return PermissionAction::values();
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [];

        foreach (self::modules() as $module) {
            foreach (self::actions() as $action) {
                $permissions[] = Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => self::GUARD,
                ]);
            }
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => self::GUARD], ['label' => 'مدير عام']);
        $superAdmin->syncPermissions($permissions);

        // The admin roster and the API keys stay with the super admin alone —
        // a lower role must not see more of them than the one above it.
        $restricted = fn (Permission $p) => str_starts_with($p->name, 'admins.')
            || str_starts_with($p->name, 'settings.');

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => self::GUARD], ['label' => 'مدير']);
        $manager->syncPermissions(collect($permissions)->reject($restricted)->all());

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => self::GUARD], ['label' => 'مطّلع']);
        $viewer->syncPermissions(
            collect($permissions)
                ->reject($restricted)
                ->filter(fn (Permission $p) => str_ends_with($p->name, '.view'))
                ->all()
        );
    }
}
