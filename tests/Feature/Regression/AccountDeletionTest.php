<?php

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\District;
use App\Models\Governorate;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\RateLimiter;

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
 * No account exists until the code proves the phone. Registration only holds
 * the signup and sends the code; verifying it creates the account, already
 * active, and signs the customer straight in.
 */
it('creates no account until the code is verified, then signs in on verify', function () {
    config(['services.otp.fake' => true]);

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

    // Nothing was written to the users table — the signup lives only in cache.
    expect($response->json('data'))->not->toHaveKey('token')
        ->and(User::where('phone', '9647766600123')->exists())->toBeFalse();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '07766600123', 'code' => '000000',
    ])->assertStatus(422)->assertJsonPath('errors.otp.0', 'INVALID_CODE');

    expect(User::where('phone', '9647766600123')->exists())->toBeFalse();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '07766600123', 'code' => OtpService::FAKE_CODE,
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'تم إنشاء حسابك بنجاح')
        ->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);

    $created = User::where('phone', '9647766600123')->sole();

    expect($created->status)->toBe(UserStatus::ACTIVE)
        ->and($created->tokens()->count())->toBe(1);

    // And the password stored through the cache still logs in — no double hash.
    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => '07766600123', 'password' => 'hoame-2026',
    ])->assertOk()->assertJsonStructure(['data' => ['user', 'token', 'token_type']]);
});

it('refuses to register a number that already has an account', function () {
    $this->postJson('/api/v1/customer/auth/register', [
        'name' => 'محاولة ثانية',
        'gender' => 'male',
        'phone' => $this->customer->phone,
        'password' => 'hoame-2026',
        'password_confirmation' => 'hoame-2026',
        'governorate_id' => $this->governorate->id,
        'district_id' => $this->district->id,
        'terms_accepted' => true,
    ])->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'رقم الهاتف مسجّل بالفعل، سجّل الدخول أو استعد كلمة السر');
});

it('resends a signup code while the signup is still in the cache', function () {
    config(['services.otp.fake' => true]);

    $this->postJson('/api/v1/customer/auth/register', [
        'name' => 'زبون جديد',
        'gender' => 'male',
        'phone' => '07766600124',
        'password' => 'hoame-2026',
        'password_confirmation' => 'hoame-2026',
        'governorate_id' => $this->governorate->id,
        'district_id' => $this->district->id,
        'terms_accepted' => true,
    ])->assertCreated();

    app('auth')->forgetGuards();

    // Past the 60-second cooldown that the first code opened.
    $this->travel(61)->seconds();

    $this->postJson('/api/v1/customer/auth/resend-otp', ['phone' => '07766600124'])
        ->assertOk()
        ->assertJsonPath('data.purpose', 'register');
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

it('never locks a customer out for logging in correctly, however often', function () {
    foreach (range(1, 25) as $ignored) {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/customer/auth/login', [
            'phone' => $this->customer->phone, 'password' => 'password',
        ])->assertOk();
    }
});

it('locks the account after five wrong passwords, then a right one is still refused', function () {
    RateLimiter::clear('customer-login:'.$this->customer->phone);

    foreach (range(1, 5) as $ignored) {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/customer/auth/login', [
            'phone' => $this->customer->phone, 'password' => 'wrong-one',
        ])->assertStatus(422)->assertJsonPath('errors.phone.0', 'رقم الهاتف أو كلمة السر غير صحيحة');
    }

    app('auth')->forgetGuards();

    // The sixth try is turned away before the password is even checked, so
    // even the correct one cannot get through until the window passes.
    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => $this->customer->phone, 'password' => 'password',
    ])->assertStatus(422)->assertJsonPath('errors.phone.0', fn ($m) => str_starts_with($m, 'محاولات دخول كثيرة'));
});

it('forgets the wrong attempts the moment the customer signs in correctly', function () {
    RateLimiter::clear('customer-login:'.$this->customer->phone);

    foreach (range(1, 4) as $ignored) {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/customer/auth/login', [
            'phone' => $this->customer->phone, 'password' => 'wrong-one',
        ])->assertStatus(422);
    }

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/customer/auth/login', [
        'phone' => $this->customer->phone, 'password' => 'password',
    ])->assertOk();

    // Four wrong then one right cleared the count; four more wrong must not trip
    // the limit, proving the tally reset rather than merely paused.
    foreach (range(1, 4) as $ignored) {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/customer/auth/login', [
            'phone' => $this->customer->phone, 'password' => 'wrong-one',
        ])->assertStatus(422)->assertJsonPath('errors.phone.0', 'رقم الهاتف أو كلمة السر غير صحيحة');
    }
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
