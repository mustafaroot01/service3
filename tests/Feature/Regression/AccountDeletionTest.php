<?php

use App\Models\Admin;
use App\Models\User;

/**
 * The request records a wish and ends the session — it must never remove the
 * account, because orders cascade off it and the history would go with them.
 */
beforeEach(function () {
    $this->customer = User::factory()->verified()->create();
    $this->token = $this->customer->createToken('app')->plainTextToken;

    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;
});

it('records the request, ends the session, and keeps the account', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/customer/profile/delete-request')
        ->assertOk()
        ->assertJsonPath('message', 'تم إرسال طلب الحذف إلى الإدارة');

    $fresh = $this->customer->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->deletion_requested_at)->not->toBeNull()
        ->and($fresh->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/customer/profile')->assertUnauthorized();
});

it('lets the customer log back in while the request is pending', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/customer/profile/delete-request')->assertOk();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => $this->customer->phone, 'password' => 'password',
    ])->assertOk();
});

it('refuses a second request while one is pending', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/customer/profile/delete-request')->assertOk();

    $token = $this->customer->fresh()->createToken('app')->plainTextToken;
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/customer/profile/delete-request')
        ->assertStatus(422)
        ->assertJsonPath('errors.account.0', 'طلب حذف الحساب مُرسل مسبقاً وقيد المراجعة');
});

it('shows the request to the admin and lets it be dismissed', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/customer/profile/delete-request')->assertOk();

    app('auth')->forgetGuards();

    $this->actingAs($this->admin, 'admin')
        ->getJson("/api/v1/admin/users/{$this->customer->id}")
        ->assertOk()
        ->assertJsonPath('data.deletion_requested', true);

    $this->actingAs($this->admin, 'admin')
        ->getJson('/api/v1/admin/users?deletion_requested=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson("/api/v1/admin/users/{$this->customer->id}/deletion-request")
        ->assertOk()
        ->assertJsonPath('data.deletion_requested', false);

    expect($this->customer->fresh()->deletion_requested_at)->toBeNull();
});

it('refuses to dismiss a request that does not exist', function () {
    $this->actingAs($this->admin, 'admin')
        ->deleteJson("/api/v1/admin/users/{$this->customer->id}/deletion-request")
        ->assertStatus(422)
        ->assertJsonPath('errors.account.0', 'لا يوجد طلب حذف على هذا الحساب');
});

it('needs a token — a visitor cannot request anyone deleted', function () {
    $this->postJson('/api/v1/customer/profile/delete-request')->assertUnauthorized();
});
