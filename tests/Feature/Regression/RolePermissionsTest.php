<?php

use App\Models\Admin;
use App\Services\RoleService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Spatie's users() relation reads guard_name off the model instance, so a plain
 * withCount('users') silently reports zero admins for every role.
 */
beforeEach(function () {
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->syncRoles(['super-admin']);

    $this->manager = Admin::factory()->create();
    $this->manager->syncRoles(['manager']);
});

it('counts the admins actually holding each role', function () {
    Admin::factory()->create()->syncRoles(['manager']);

    $roles = collect(app(RoleService::class)->list())->keyBy('name');

    expect($roles['super-admin']['admins_count'])->toBe(1)
        ->and($roles['manager']['admins_count'])->toBe(2)
        ->and($roles['viewer']['admins_count'])->toBe(0);
});

it('reports how many permissions each role holds out of the total', function () {
    $roles = collect(app(RoleService::class)->list())->keyBy('name');
    $total = $roles['super-admin']['permissions_total'];

    expect($roles['super-admin']['permissions_count'])->toBe($total)
        ->and($roles['viewer']['permissions_count'])->toBeLessThan($total)
        ->and($roles['viewer']['permissions_count'])->toBeGreaterThan(0);
});

it('offers every module and action in the catalogue', function () {
    $catalog = app(RoleService::class)->catalog();

    expect($catalog['actions'])->toHaveCount(4)
        ->and(collect($catalog['modules'])->pluck('key'))->toContain('orders', 'technicians', 'technician-applications')
        ->and(collect($catalog['modules'])->every(fn ($m) => $m['actions'] !== []))->toBeTrue();
});

it('creates a role with the permissions it was given', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/roles', [
            'label' => 'مشرف المحتوى',
            'permissions' => ['blog.view', 'blog.update', 'sliders.view'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'مشرف المحتوى')
        ->assertJsonPath('data.permissions_count', 3)
        ->assertJsonPath('data.admins_count', 0)
        ->assertJsonPath('data.is_locked', false);

    $role = Role::where('label', 'مشرف المحتوى')->sole();

    expect($role->guard_name)->toBe('admin')
        ->and($role->name)->not->toBeEmpty()
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['blog.update', 'blog.view', 'sliders.view']);
});

it('gives every generated role a distinct identifier', function () {
    foreach (range(1, 3) as $i) {
        $this->actingAs($this->superAdmin, 'admin')
            ->postJson('/api/v1/admin/roles', ['label' => "دور رقم {$i}", 'permissions' => []])
            ->assertCreated();
    }

    $names = Role::where('guard_name', 'admin')->pluck('name');

    expect($names->unique())->toHaveCount($names->count());
});

it('refuses two roles with the same name', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/roles', ['label' => 'مشرف المحتوى', 'permissions' => []])->assertCreated();

    $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/roles', ['label' => 'مشرف المحتوى', 'permissions' => []])
        ->assertStatus(422)
        ->assertJsonPath('errors.label.0', 'يوجد دور بهذا الاسم');
});

it('deletes a role nobody holds', function () {
    $id = $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/roles', ['label' => 'دور مؤقت', 'permissions' => ['blog.view']])
        ->json('data.id');

    $this->actingAs($this->superAdmin, 'admin')
        ->deleteJson("/api/v1/admin/roles/{$id}")->assertOk();

    expect(Role::find($id))->toBeNull()
        ->and(DB::table('role_has_permissions')->where('role_id', $id)->count())->toBe(0);
});

it('refuses to delete a role that admins still hold', function () {
    $role = Role::where('name', 'manager')->first();

    $this->actingAs($this->superAdmin, 'admin')
        ->deleteJson("/api/v1/admin/roles/{$role->id}")
        ->assertStatus(422)
        ->assertJsonPath('errors.role.0', 'الدور مُسند إلى 1 مشرف، انقلهم إلى دور آخر قبل الحذف');

    expect(Role::find($role->id))->not->toBeNull()
        ->and($this->manager->fresh()->can('orders.view'))->toBeTrue();
});

it('refuses to delete the super admin role before anything else', function () {
    $role = Role::where('name', RoleService::LOCKED_ROLE)->first();

    $this->actingAs($this->superAdmin, 'admin')
        ->deleteJson("/api/v1/admin/roles/{$role->id}")
        ->assertStatus(422)
        ->assertJsonPath('errors.role.0', 'دور المدير العام لا يُحذف');
});

it('saves a new permission set for a role', function () {
    $role = Role::where('name', 'viewer')->first();

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/roles/{$role->id}", ['label' => 'مطّلع', 'permissions' => ['orders.view', 'orders.update']])
        ->assertOk()
        ->assertJsonPath('data.permissions_count', 2);

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['orders.update', 'orders.view']);
});

it('refuses to strip the super admin of its permissions', function () {
    $role = Role::where('name', 'super-admin')->first();

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/roles/{$role->id}", ['label' => 'مدير عام', 'permissions' => []])
        ->assertStatus(422)
        ->assertJsonPath('errors.permissions.0', 'دور المدير العام يملك كل الصلاحيات ولا يمكن تعديله');

    expect($role->fresh()->permissions)->not->toBeEmpty();
});

it('rejects a permission name that does not exist', function () {
    $role = Role::where('name', 'viewer')->first();

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/roles/{$role->id}", ['label' => 'مطّلع', 'permissions' => ['orders.view', 'orders.explode']])
        ->assertStatus(422);

    expect($role->fresh()->permissions->pluck('name'))->not->toContain('orders.explode');
});

it('keeps a manager out of the roles screen', function () {
    $this->actingAs($this->manager, 'admin')->getJson('/api/v1/admin/roles')->assertForbidden();

    $this->actingAs($this->manager, 'admin')
        ->putJson('/api/v1/admin/roles/'.Role::where('name', 'viewer')->first()->id, ['label' => 'مطّلع', 'permissions' => []])
        ->assertForbidden();

    $this->actingAs($this->manager, 'admin')
        ->postJson('/api/v1/admin/roles', ['label' => 'دور مهرّب', 'permissions' => []])->assertForbidden();

    $this->actingAs($this->manager, 'admin')
        ->deleteJson('/api/v1/admin/roles/'.Role::where('name', 'viewer')->first()->id)->assertForbidden();
});

it('takes effect immediately on the next request', function () {
    $viewer = Admin::factory()->create();
    $viewer->syncRoles(['viewer']);
    $role = Role::where('name', 'viewer')->first();

    $this->actingAs($viewer, 'admin')->getJson('/api/v1/admin/orders')->assertOk();

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/roles/{$role->id}", ['label' => 'مطّلع', 'permissions' => ['users.view']])
        ->assertOk();

    $this->actingAs($viewer->fresh(), 'admin')->getJson('/api/v1/admin/orders')->assertForbidden();
});
