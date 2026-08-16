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

/**
 * Signup opens nothing on its own: the account waits on a code, and the code
 * only opens it — the session still has to be earned on the login screen.
 */
it('holds a new signup at pending until the code arrives, then still asks for a login', function () {
    $signup = [
        'name' => 'زبون جديد',
        'gender' => 'male',
        'phone' => '07766600123',
        'password' => 'hoame-2026',
        'password_confirmation' => 'hoame-2026',
        'governorate_id' => $this->governorate->id,
        'district_id' => $this->district->id,
        'terms_accepted' => true,
    ];

    $response = $this->postJson('/api/v1/customer/auth/register', $signup)
        ->assertCreated()
        ->assertJsonPath('data.phone', '9647766600123')
        ->assertJsonPath('message', 'أرسلنا رمز التحقق إلى واتساب');

    expect($response->json('data'))->not->toHaveKey('token');

    $created = User::where('phone', '9647766600123')->sole();

    expect($created->status)->toBe(UserStatus::PENDING)
        ->and($created->phone_verified_at)->toBeNull()
        ->and($created->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => '07766600123', 'password' => 'hoame-2026',
    ])->assertStatus(422)->assertJsonPath('errors.phone.0', 'لم يتم توثيق رقمك بعد، اطلب رمز التحقق');

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '07766600123', 'code' => '000000',
    ])->assertStatus(422)->assertJsonPath('errors.otp.0', 'INVALID_CODE');

    app('auth')->forgetGuards();

    $verified = $this->postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '07766600123', 'code' => OtpService::FAKE_CODE,
    ])->assertOk()->assertJsonPath('message', 'تم توثيق رقمك، سجّل الدخول للمتابعة');

    expect($verified->json('data'))->not->toHaveKey('token');

    $created->refresh();

    expect($created->status)->toBe(UserStatus::ACTIVE)
        ->and($created->phone_verified_at)->not->toBeNull()
        ->and($created->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => '07766600123', 'password' => 'hoame-2026',
    ])->assertOk()->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);
});

it('refuses to verify a number that is already verified', function () {
    $this->postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => $this->customer->phone, 'code' => OtpService::FAKE_CODE,
    ])->assertStatus(422)->assertJsonPath('errors.phone.0', 'هذا الرقم موثّق بالفعل، سجّل الدخول');
});

it('sends a signup code, not a reset code, while the account is still pending', function () {
    $pending = User::factory()->create(['phone' => '9647766600124']);
    $pending->forceFill(['status' => UserStatus::PENDING, 'phone_verified_at' => null])->save();

    $this->postJson('/api/v1/customer/auth/resend-otp', ['phone' => '07766600124'])
        ->assertOk()
        ->assertJsonPath('data.purpose', 'register');

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/forgot-password', ['phone' => '07766600124'])
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'لم يتم توثيق رقمك بعد، اطلب رمز التحقق');
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

/**
 * The code proves a phone number, and nothing about it should overrule an
 * admin who closed the account while the customer was still finishing signup.
 */
it('verifies the phone without reopening an account the admin closed', function () {
    $held = User::factory()->create(['phone' => '9647755566677']);
    $held->forceFill(['status' => UserStatus::SUSPENDED, 'phone_verified_at' => null])->save();

    \App\Models\PhoneVerification::create([
        'phone' => '9647755566677',
        'message_id' => 'fake',
        'purpose' => \App\Enums\OtpPurpose::REGISTER,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '07755566677', 'code' => OtpService::FAKE_CODE,
    ])->assertOk();

    $held->refresh();

    expect($held->phone_verified_at)->not->toBeNull()
        ->and($held->status)->toBe(UserStatus::SUSPENDED);

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => '07755566677', 'password' => 'password',
    ])->assertStatus(422)->assertJsonPath('errors.phone.0', 'حسابك غير مفعّل، راجع الإدارة');
});

it('refuses to spend a message on an account that could not log in with it', function () {
    $closed = User::factory()->verified()->create(['phone' => '9647755566688']);

    foreach ([
        [UserStatus::SCHEDULED_FOR_DELETION, 'حسابك مجدول للحذف'],
        [UserStatus::SUSPENDED, 'حسابك غير مفعّل، راجع الإدارة'],
    ] as [$status, $message]) {
        $closed->forceFill(['status' => $status])->save();

        foreach (['resend-otp', 'forgot-password'] as $endpoint) {
            app('auth')->forgetGuards();

            $this->postJson("/api/v1/customer/auth/{$endpoint}", ['phone' => '07755566688'])
                ->assertStatus(422)
                ->assertJsonPath('errors.phone.0', $message);
        }
    }
});
