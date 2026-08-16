<?php

use App\Enums\SettingKey;
use App\Models\Admin;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;

/**
 * The panel shows secrets masked. Saving that mask back must never wipe the key,
 * and a value the app can no longer decrypt — the signature of a rotated
 * APP_KEY — must not take down the one screen that can fix it.
 */
beforeEach(function () {
    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;
    $this->settings = app(SettingService::class);
});

$save = fn ($test, array $pairs) => $test->actingAs($test->admin, 'admin')
    ->putJson('/api/v1/admin/settings', [
        'settings' => collect($pairs)->map(fn ($v, $k) => ['key' => $k, 'value' => $v])->values()->all(),
    ]);

it('keeps the real key when the mask is saved back', function () use ($save) {
    $this->settings->set(SettingKey::OTP_API_KEY, 'otplive_REAL_SECRET_9999');

    $masked = collect($this->settings->presentGrouped())
        ->flatMap(fn ($g) => $g['items'])
        ->firstWhere('key', 'otp_api_key')['value'];

    expect($masked)->toBe('••••••••9999');

    $save($this, ['otp_api_key' => $masked])->assertOk();

    expect(app(SettingService::class)->get(SettingKey::OTP_API_KEY))->toBe('otplive_REAL_SECRET_9999');
});

it('clears a setting when the admin empties the field', function () use ($save) {
    $this->settings->set(SettingKey::OTP_BASE_URL, 'https://otp.arqam.tech/api');

    $save($this, ['otp_base_url' => ''])->assertOk();

    expect(app(SettingService::class)->get(SettingKey::OTP_BASE_URL))->toBeNull();
});

it('leaves a setting untouched when its field is absent', function () {
    $this->settings->set(SettingKey::OTP_BASE_URL, 'https://otp.arqam.tech/api');

    $this->actingAs($this->admin, 'admin')
        ->putJson('/api/v1/admin/settings', ['settings' => [['key' => 'onesignal_app_id', 'value' => 'abc']]])
        ->assertOk();

    expect(app(SettingService::class)->get(SettingKey::OTP_BASE_URL))->toBe('https://otp.arqam.tech/api');
});

it('still opens the panel when a stored value will not decrypt', function () {
    $this->settings->set(SettingKey::OTP_API_KEY, 'otplive_REAL_SECRET_9999');
    DB::table('settings')->where('key', 'otp_api_key')->update(['value' => 'eyJpdiI6IkdBUkJBR0UifQ==']);

    $item = collect($this->actingAs($this->admin, 'admin')->getJson('/api/v1/admin/settings')->assertOk()->json('data'))
        ->flatMap(fn ($g) => $g['items'])
        ->firstWhere('key', 'otp_api_key');

    expect($item['is_readable'])->toBeFalse()
        ->and($item['is_set'])->toBeFalse()
        ->and($item['value'])->toBeNull();
});

it('reports an undecryptable value as unset instead of throwing', function () {
    $this->settings->set(SettingKey::OTP_API_KEY, 'otplive_REAL_SECRET_9999');
    DB::table('settings')->where('key', 'otp_api_key')->update(['value' => 'eyJpdiI6IkdBUkJBR0UifQ==']);

    expect(app(SettingService::class)->get(SettingKey::OTP_API_KEY))->toBeNull();
});

it('treats an unconfigured messaging service as a 503 outage, not a field error', function () {
    config(['services.otp.fake' => false]);
    Setting::query()->delete();
    User::factory()->verified()->create(['phone' => '9647801111111']);

    // Provider-side fault: a 503 the app shows as "try later", and with no
    // errors.otp so it never reddens the phone field as if the customer erred.
    $this->postJson('/api/v1/customer/auth/forgot-password', ['phone' => '07801111111'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'خدمة الرسائل غير مهيّأة، راجع الإدارة')
        ->assertJsonPath('errors', []);
});

it('reads every setting in a single query', function () {
    $this->settings->set(SettingKey::OTP_BASE_URL, 'https://otp.arqam.tech/api');
    $this->settings->set(SettingKey::OTP_API_KEY, 'k');

    $service = app(SettingService::class);
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $service->get(SettingKey::OTP_BASE_URL);
    $service->get(SettingKey::OTP_API_KEY);
    $service->get(SettingKey::ONESIGNAL_APP_ID);
    $service->get(SettingKey::ONESIGNAL_REST_API_KEY);

    expect($queries)->toBe(1);
});

it('keeps a customer OTP mistake as a 422 with a field error', function () {
    config(['services.otp.fake' => true]);
    $customer = User::factory()->verified()->create(['phone' => '9647802222222']);

    app(App\Services\OtpService::class)->send('07802222222', App\Enums\OtpPurpose::RESET);

    // Wrong code is the caller's fault: 422, and errors.otp so the field flags it.
    $this->postJson('/api/v1/customer/auth/reset-password', [
        'phone' => '07802222222', 'code' => '000000',
        'password' => 'new-pass-123', 'password_confirmation' => 'new-pass-123',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.otp.0', 'INVALID_CODE');
});
