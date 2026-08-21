<?php

use App\Enums\OtpPurpose;
use App\Enums\SettingKey;
use App\Models\PhoneVerification;
use App\Services\OtpService;
use App\Services\SettingService;
use Illuminate\Support\Facades\Http;

/**
 * The host has no scheduler, so `phone_verifications` cleans itself: every send
 * drops rows already past their expiry, while a still-valid code is left alone.
 * Codes now live three minutes.
 */
beforeEach(function () {
    config()->set('services.otp.fake', false);

    $settings = app(SettingService::class);
    $settings->set(SettingKey::OTP_BASE_URL, 'https://otp.test/api');
    $settings->set(SettingKey::OTP_API_KEY, 'test-key');

    Http::fake(['*/sms/otp' => Http::response(['messageId' => 'fresh'], 200)]);
});

it('drops expired rows and keeps valid ones when a code is sent', function () {
    $expired = PhoneVerification::create([
        'phone' => '9647701111111', 'message_id' => 'old',
        'purpose' => OtpPurpose::REGISTER, 'expires_at' => now()->subMinutes(5),
    ]);
    $valid = PhoneVerification::create([
        'phone' => '9647703333333', 'message_id' => 'live',
        'purpose' => OtpPurpose::REGISTER, 'expires_at' => now()->addMinutes(2),
    ]);

    app(OtpService::class)->send('07702222222', OtpPurpose::REGISTER);

    expect(PhoneVerification::whereKey($expired->id)->exists())->toBeFalse()
        ->and(PhoneVerification::whereKey($valid->id)->exists())->toBeTrue();
});

it('issues a code that lives three minutes', function () {
    app(OtpService::class)->send('07702222222', OtpPurpose::REGISTER);

    $fresh = PhoneVerification::where('message_id', 'fresh')->firstOrFail();

    expect($fresh->expires_at->isFuture())->toBeTrue()
        ->and($fresh->expires_at->lessThanOrEqualTo(now()->addMinutes(3)))->toBeTrue()
        ->and($fresh->expires_at->greaterThan(now()->addMinutes(2)))->toBeTrue();
});
