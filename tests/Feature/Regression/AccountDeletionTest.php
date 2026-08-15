<?php

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\District;
use App\Models\Governorate;
use App\Models\User;
use App\Services\OtpService;

/**
 * The request records a wish and ends the session — it must never remove the
 * account, because orders cascade off it and the history would go with them.
 */
beforeEach(function () {
    $this->governorate = Governorate::create(['name' => 'بغداد', 'is_active' => true]);
    $this->district = District::create([
        'governorate_id' => $this->governorate->id, 'name' => 'الكرخ', 'is_active' => true,
    ]);

    $this->customer = User::factory()->verified()->create();
    $this->token = $this->customer->createToken('app')->plainTextToken;

    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;
});

it('marks the account scheduled for deletion, ends the session, and keeps the data', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/customer/profile/delete-request')
        ->assertOk()
        ->assertJsonPath('message', 'تم إرسال طلب الحذف إلى الإدارة');

    $fresh = $this->customer->fresh();

    expect($fresh)->not->toBeNull()
        ->and($fresh->status)->toBe(UserStatus::SCHEDULED_FOR_DELETION)
        ->and($fresh->deletion_requested_at)->not->toBeNull()
        ->and($fresh->tokens()->count())->toBe(0)
        // Nothing is removed — the panel keeps counting him and his orders.
        ->and($fresh->name)->toBe($this->customer->name)
        ->and($fresh->phone)->toBe($this->customer->phone);

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/customer/profile')->assertUnauthorized();
});

it('refuses the login and says the account is scheduled for deletion', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/customer/profile/delete-request')->assertOk();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => $this->customer->phone, 'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'حسابك مجدول للحذف');
});

it('keeps the orders and the counters untouched', function () {
    $before = User::count();

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/customer/profile/delete-request')->assertOk();

    expect(User::count())->toBe($before)
        ->and(User::find($this->customer->id))->not->toBeNull();
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

it('reopens the account when the admin dismisses the request', function () {
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
        ->assertJsonPath('data.deletion_requested', false)
        ->assertJsonPath('data.status', 'active');

    expect($this->customer->fresh()->deletion_requested_at)->toBeNull();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => $this->customer->phone, 'password' => 'password',
    ])->assertOk();
});

it('clears the request when the admin sets any other status', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/customer/profile/delete-request')->assertOk();

    app('auth')->forgetGuards();

    $this->actingAs($this->admin, 'admin')
        ->patchJson("/api/v1/admin/users/{$this->customer->id}/status", ['status' => 'active'])
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

it('opens the account at signup but hands back no session', function () {
    $response = $this->postJson('/api/v1/customer/auth/register', [
        'name' => 'زبون جديد',
        'gender' => 'male',
        'phone' => '07766600123',
        'password' => 'hoame-2026',
        'password_confirmation' => 'hoame-2026',
        'governorate_id' => $this->governorate->id,
        'district_id' => $this->district->id,
        'terms_accepted' => true,
    ])->assertCreated()->assertJsonPath('data.phone', '9647766600123');

    expect($response->json('data'))->not->toHaveKey('token');

    $created = User::where('phone', '9647766600123')->sole();

    expect($created->phone_verified_at)->not->toBeNull()
        ->and($created->status)->toBe(UserStatus::ACTIVE)
        ->and($created->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => '07766600123', 'password' => 'hoame-2026',
    ])->assertOk()->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);
});

it('refuses to send a code to a number that has no account', function () {
    foreach (['resend-otp', 'forgot-password'] as $endpoint) {
        app('auth')->forgetGuards();

        $this->postJson("/api/v1/customer/auth/{$endpoint}", ['phone' => '07733399999'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }
});

it('sends the customer back to the login screen after a password reset', function () {
    config(['services.otp.fake' => true]);

    $this->customer->forceFill(['phone_verified_at' => now()])->save();

    $this->postJson('/api/v1/customer/auth/forgot-password', ['phone' => $this->customer->phone])
        ->assertOk();

    app('auth')->forgetGuards();

    $response = $this->postJson('/api/v1/customer/auth/reset-password', [
        'phone' => $this->customer->phone,
        'code' => OtpService::FAKE_CODE,
        'password' => 'hoame-2027',
        'password_confirmation' => 'hoame-2027',
    ])->assertOk();

    expect($response->json('data'))->toBeNull()
        ->and($this->customer->fresh()->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => $this->customer->phone, 'password' => 'hoame-2027',
    ])->assertOk();
});

it('does not throttle repeated logins', function () {
    foreach (range(1, 25) as $ignored) {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/customer/auth/login', [
            'phone' => $this->customer->phone, 'password' => 'password',
        ])->assertOk();
    }
});
