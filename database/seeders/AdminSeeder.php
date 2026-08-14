<?php

namespace Database\Seeders;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Admin::firstOrCreate(
            ['email' => 'superadmin@hoame.test'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'status' => AdminStatus::ACTIVE,
            ]
        );
        $superAdmin->syncRoles(['super-admin']);

        $manager = Admin::firstOrCreate(
            ['email' => 'admin@hoame.test'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'status' => AdminStatus::ACTIVE,
            ]
        );
        $manager->syncRoles(['manager']);

        $viewer = Admin::firstOrCreate(
            ['email' => 'viewer@hoame.test'],
            [
                'name' => 'Viewer',
                'password' => 'password',
                'status' => AdminStatus::ACTIVE,
            ]
        );
        $viewer->syncRoles(['viewer']);
    }
}
