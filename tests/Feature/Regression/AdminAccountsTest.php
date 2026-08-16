<?php

use App\Enums\AdminStatus;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Every guard here exists to stop the panel from being locked out: an admin
 * who demotes himself, or the last super admin being deleted or disabled.
 */
beforeEach(function () {
    $this->superAdmin = Admin::factory()->create(['status' => AdminStatus::ACTIVE]);
    $this->superAdmin->syncRoles(['super-admin']);

    $this->manager = Admin::factory()->create(['status' => AdminStatus::ACTIVE]);
    $this->manager->syncRoles(['manager']);

    $this->viewerRole = Role::where('name', 'viewer')->first();
    $this->managerRole = Role::where('name', 'manager')->first();
    $this->superRole = Role::where('name', 'super-admin')->first();
});

it('creates an admin account with a role and a working password', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/admins', [
            'name' => 'مشرف المحتوى',
            'email' => 'content@hoame.test',
            'password' => 'sup3r-secret',
            'role_id' => $this->viewerRole->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.role.label', 'مطّلع')
        ->assertJsonPath('data.status', 'active');

    $created = Admin::where('email', 'content@hoame.test')->sole();

    expect($created->password)->not->toBe('sup3r-secret')
        ->and(Hash::check('sup3r-secret', $created->password))->toBeTrue()
        ->and($created->hasRole('viewer'))->toBeTrue();

    $this->postJson('/api/v1/admin/auth/login', ['email' => 'content@hoame.test', 'password' => 'sup3r-secret'])
        ->assertOk();
});

it('refuses a duplicate email and a short password', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/admins', [
            'name' => 'نسخة', 'email' => $this->manager->email,
            'password' => 'sup3r-secret', 'role_id' => $this->viewerRole->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'البريد الإلكتروني مستخدم لحساب آخر');

    $this->actingAs($this->superAdmin, 'admin')
        ->postJson('/api/v1/admin/admins', [
            'name' => 'قصيرة', 'email' => 'short@hoame.test',
            'password' => '123', 'role_id' => $this->viewerRole->id,
        ])
        ->assertStatus(422);
});

it('keeps the current password when the field is left empty', function () {
    $before = $this->manager->password;

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/admins/{$this->manager->id}", [
            'name' => 'اسم جديد', 'email' => $this->manager->email,
            'password' => '', 'role_id' => $this->managerRole->id, 'status' => 'active',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'اسم جديد');

    expect($this->manager->fresh()->password)->toBe($before);
});

it('changes a role and the new permissions apply at once', function () {
    expect($this->manager->can('orders.create'))->toBeTrue();

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/admins/{$this->manager->id}", [
            'name' => $this->manager->name, 'email' => $this->manager->email,
            'password' => '', 'role_id' => $this->viewerRole->id, 'status' => 'active',
        ])
        ->assertOk()
        ->assertJsonPath('data.role.label', 'مطّلع');

    expect($this->manager->fresh()->can('orders.create'))->toBeFalse()
        ->and($this->manager->fresh()->can('orders.view'))->toBeTrue();
});

it('stops an admin from changing his own role', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/admins/{$this->superAdmin->id}", [
            'name' => $this->superAdmin->name, 'email' => $this->superAdmin->email,
            'password' => '', 'role_id' => $this->viewerRole->id, 'status' => 'active',
        ])
        ->assertStatus(422);

    expect($this->superAdmin->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('stops an admin from deleting or disabling himself', function () {
    $this->actingAs($this->manager, 'admin')
        ->deleteJson("/api/v1/admin/admins/{$this->manager->id}")
        ->assertStatus(403);

    $this->actingAs($this->superAdmin, 'admin')
        ->deleteJson("/api/v1/admin/admins/{$this->superAdmin->id}")
        ->assertStatus(422)
        ->assertJsonPath('errors.admin.0', 'لا يمكنك حذف حسابك');

    $this->actingAs($this->superAdmin, 'admin')
        ->patchJson("/api/v1/admin/admins/{$this->superAdmin->id}/status", ['status' => 'suspended'])
        ->assertStatus(422);

    expect($this->superAdmin->fresh()->status)->toBe(AdminStatus::ACTIVE);
});

it('protects the last active super admin', function () {
    $second = Admin::factory()->create(['status' => AdminStatus::ACTIVE]);
    $second->syncRoles(['super-admin']);

    // With two of them the guard stays out of the way.
    $this->actingAs($this->superAdmin, 'admin')
        ->patchJson("/api/v1/admin/admins/{$second->id}/status", ['status' => 'suspended'])
        ->assertOk();

    // Now only one is left, so it cannot be disabled or deleted.
    $this->actingAs($second, 'admin')
        ->patchJson("/api/v1/admin/admins/{$this->superAdmin->id}/status", ['status' => 'inactive'])
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'هذا آخر مدير عام نشط، لا يمكن تعطيله');

    $this->actingAs($second, 'admin')
        ->deleteJson("/api/v1/admin/admins/{$this->superAdmin->id}")
        ->assertStatus(422);
});

it('drops the tokens of an account it disables', function () {
    $token = $this->manager->createToken('panel')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/orders')->assertOk();

    $this->actingAs($this->superAdmin, 'admin')
        ->patchJson("/api/v1/admin/admins/{$this->manager->id}/status", ['status' => 'suspended'])
        ->assertOk();

    // actingAs pins a user for the rest of the test and would mask the token.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/orders')->assertUnauthorized();
});

it('deletes an account together with its tokens and role link', function () {
    $this->manager->createToken('panel');

    $this->actingAs($this->superAdmin, 'admin')
        ->deleteJson("/api/v1/admin/admins/{$this->manager->id}")
        ->assertOk();

    expect(Admin::find($this->manager->id))->toBeNull()
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $this->manager->id)->where('tokenable_type', 'admin')->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_id', $this->manager->id)->count())->toBe(0);
});

it('keeps a manager away from admin accounts entirely', function () {
    $this->actingAs($this->manager, 'admin')->getJson('/api/v1/admin/admins')->assertForbidden();

    $this->actingAs($this->manager, 'admin')
        ->postJson('/api/v1/admin/admins', [
            'name' => 'مهرّب', 'email' => 'x@hoame.test',
            'password' => 'sup3r-secret', 'role_id' => $this->superRole->id,
        ])
        ->assertForbidden();
});

it('cuts off a customer the moment his account is disabled', function () {
    $customer = User::factory()->verified()->create(['status' => UserStatus::ACTIVE]);
    $token = $customer->createToken('app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/customer/profile')->assertOk();

    $this->actingAs($this->superAdmin, 'admin')
        ->patchJson("/api/v1/admin/users/{$customer->id}/status", ['status' => 'suspended'])
        ->assertOk();

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/customer/profile')->assertUnauthorized();
});

/**
 * Status is only read at the login door. Whatever revokes access must also
 * take away the key the admin is already holding, or "disabled" means nothing
 * until he chooses to log out.
 */
it('cuts off an admin disabled through the edit form, not only through the status button', function () {
    $token = $this->manager->createToken('admin-token')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/users?per_page=1')->assertOk();

    app('auth')->forgetGuards();

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/admins/{$this->manager->id}", [
            'name' => $this->manager->name,
            'email' => $this->manager->email,
            'status' => AdminStatus::INACTIVE->value,
            'role_id' => $this->managerRole->id,
        ])->assertOk();

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/users?per_page=1')->assertUnauthorized();
});

it('ends the session when an admin is moved to another role', function () {
    $token = $this->manager->createToken('admin-token')->plainTextToken;

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/admins/{$this->manager->id}", [
            'name' => $this->manager->name,
            'email' => $this->manager->email,
            'status' => AdminStatus::ACTIVE->value,
            'role_id' => $this->viewerRole->id,
        ])->assertOk();

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/users?per_page=1')->assertUnauthorized();
});

it('ends the session when an admin password is changed for him', function () {
    $token = $this->manager->createToken('admin-token')->plainTextToken;

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/admins/{$this->manager->id}", [
            'name' => $this->manager->name,
            'email' => $this->manager->email,
            'status' => AdminStatus::ACTIVE->value,
            'role_id' => $this->managerRole->id,
            'password' => 'a-brand-new-one',
        ])->assertOk();

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/users?per_page=1')->assertUnauthorized();
});

it('keeps the session alive when nothing about access changed', function () {
    $token = $this->manager->createToken('admin-token')->plainTextToken;

    $this->actingAs($this->superAdmin, 'admin')
        ->putJson("/api/v1/admin/admins/{$this->manager->id}", [
            'name' => 'اسم جديد',
            'email' => $this->manager->email,
            'status' => AdminStatus::ACTIVE->value,
            'role_id' => $this->managerRole->id,
        ])->assertOk();

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/users?per_page=1')->assertOk();
});

/**
 * A verified number is somebody's account and nobody may sign up over it. An
 * unverified one is an abandoned signup, so it may be claimed — but only by
 * whoever can read the code sent to that phone.
 */
it('refuses to sign up over a verified number, and gates an unverified one behind the code', function () {
    $governorate = \App\Models\Governorate::create(['name' => 'بغداد', 'is_active' => true]);
    $district = \App\Models\District::create([
        'governorate_id' => $governorate->id, 'name' => 'الكرخ', 'is_active' => true,
    ]);

    $signup = [
        'name' => 'مهاجم',
        'gender' => 'female',
        'phone' => '07712345678',
        'password' => 'attacker-pass',
        'password_confirmation' => 'attacker-pass',
        'governorate_id' => $governorate->id,
        'district_id' => $district->id,
        'terms_accepted' => true,
    ];

    $owner = User::factory()->verified()->create([
        'name' => 'صاحب الحساب الأصلي',
        'phone' => '9647712345678',
        'password' => 'his-own-password',
    ]);

    $this->postJson('/api/v1/customer/auth/register', $signup)
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'رقم الهاتف مسجّل بالفعل، سجّل الدخول أو استعد كلمة السر');

    expect($owner->fresh()->name)->toBe('صاحب الحساب الأصلي');

    // Now the same number, abandoned before verification.
    $owner->forceFill(['status' => UserStatus::PENDING, 'phone_verified_at' => null])->save();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/register', $signup)->assertCreated();

    app('auth')->forgetGuards();

    // Claimed on paper, but useless without the code.
    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => '07712345678', 'password' => 'attacker-pass',
    ])->assertStatus(422)->assertJsonPath('errors.phone.0', 'لم يتم توثيق رقمك بعد، اطلب رمز التحقق');

    expect(User::where('phone', '9647712345678')->sole()->status)->toBe(UserStatus::PENDING);
});
