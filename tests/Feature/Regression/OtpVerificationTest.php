<?php

use App\Enums\OtpPurpose;
use App\Enums\SettingKey;
use App\Exceptions\OtpException;
use App\Models\PhoneVerification;
use App\Services\OtpService;
use App\Services\SettingService;
use Illuminate\Support\Facades\Http;

/**
 * Arqam answers HTTP 200 with {"success": false} when it rejects a code.
 * Trusting the status line alone let ANY code verify ANY phone.
 */
beforeEach(function () {
    config()->set('services.otp.fake', false);

    $settings = app(SettingService::class);
    $settings->set(SettingKey::OTP_BASE_URL, 'https://otp.test/api');
    $settings->set(SettingKey::OTP_API_KEY, 'test-key');

    PhoneVerification::create([
        'phone' => '9647700000001',
        'message_id' => 'msg-1',
        'purpose' => OtpPurpose::REGISTER,
        'expires_at' => now()->addMinutes(10),
    ]);
});

it('rejects a wrong code even when the provider answers HTTP 200', function () {
    Http::fake(['*/sms/verify' => Http::response(['success' => false, 'message' => 'Invalid OTP code'], 200)]);

    expect(fn () => app(OtpService::class)->verify('07700000001', OtpPurpose::REGISTER, '000000'))
        ->toThrow(OtpException::class, 'الرمز غير صحيح');

    expect(PhoneVerification::first()->verified_at)->toBeNull();
});

it('maps an expired provider message to the expiry error', function () {
    Http::fake(['*/sms/verify' => Http::response(['success' => false, 'message' => 'Code expired'], 200)]);

    expect(fn () => app(OtpService::class)->verify('07700000001', OtpPurpose::REGISTER, '000000'))
        ->toThrow(OtpException::class, 'انتهت صلاحية الرمز، اطلب رمزاً جديداً');
});

it('accepts a correct code', function () {
    Http::fake(['*/sms/verify' => Http::response(['success' => true], 200)]);

    $verification = app(OtpService::class)->verify('07700000001', OtpPurpose::REGISTER, '536156');

    expect($verification->verified_at)->not->toBeNull();
});

it('refuses to store a message id when the provider rejects the send', function () {
    Http::fake(['*/sms/otp' => Http::response(['success' => false, 'message' => 'Insufficient credits'], 200)]);

    expect(fn () => app(OtpService::class)->send('07700000002', OtpPurpose::REGISTER))
        ->toThrow(OtpException::class);

    expect(PhoneVerification::where('phone', '9647700000002')->exists())->toBeFalse();
});
