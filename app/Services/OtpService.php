<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Enums\SettingKey;
use App\Exceptions\OtpException;
use App\Models\PhoneVerification;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public const FAKE_CODE = '123456';

    public const RESEND_COOLDOWN_SECONDS = 60;

    private const TTL_MINUTES = 3;

    public function __construct(private readonly SettingService $settings) {}

    public function send(string $phone, OtpPurpose $purpose): PhoneVerification
    {
        $phone = Phone::international($phone) ?? throw new OtpException('INVALID_PHONE');

        $this->pruneExpired();

        $this->guardCooldown($phone, $purpose);

        $messageId = $this->isFake()
            ? $this->fakeSend($phone, $purpose)
            : $this->providerSend($phone, $purpose);

        return PhoneVerification::create([
            'phone' => $phone,
            'message_id' => $messageId,
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);
    }

    public function verify(string $phone, OtpPurpose $purpose, string $code): PhoneVerification
    {
        $phone = Phone::international($phone) ?? throw new OtpException('INVALID_PHONE');

        $verification = PhoneVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $verification) {
            throw new OtpException('INVALID_CODE');
        }

        if ($verification->expires_at?->isPast()) {
            throw new OtpException('EXPIRED_CODE');
        }

        $this->isFake()
            ? $this->fakeVerify($code)
            : $this->providerVerify($verification->message_id, $code);

        $verification->forceFill(['verified_at' => now()])->save();

        return $verification;
    }

    public function secondsUntilResend(string $phone, OtpPurpose $purpose): int
    {
        $phone = Phone::international($phone);

        if (! $phone) {
            return 0;
        }

        $last = PhoneVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if (! $last?->created_at) {
            return 0;
        }

        $elapsed = $last->created_at->diffInSeconds(now());

        return (int) max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    public function isFake(): bool
    {
        return ! app()->isProduction() && config('services.otp.fake') === true;
    }

    /**
     * Self-cleaning: every send drops rows already past their expiry, so the
     * table never grows without bound — the host has no scheduler to prune it.
     * Only expired rows go; a still-valid code the customer is about to enter is
     * never touched.
     */
    private function pruneExpired(): void
    {
        PhoneVerification::where('expires_at', '<', now())->delete();
    }

    private function guardCooldown(string $phone, OtpPurpose $purpose): void
    {
        $remaining = $this->secondsUntilResend($phone, $purpose);

        if ($remaining > 0) {
            throw new OtpException('COOLDOWN', "انتظر {$remaining} ثانية قبل إعادة إرسال الرمز");
        }
    }

    private function providerSend(string $phone, OtpPurpose $purpose): ?string
    {
        $response = $this->post($purpose->endpoint(), [
            'phoneNumber' => $phone,
        ]);

        if (! $this->succeeded($response)) {
            Log::warning('OTP send failed', ['status' => $response->status(), 'body' => $response->json()]);

            throw OtpException::fromProvider(
                $response->json('code'),
                $response->json('message') ?? $response->json('error')
            );
        }

        return $response->json('messageId')
            ?? $response->json('data.messageId')
            ?? $response->json('id');
    }

    private function providerVerify(?string $messageId, string $code): void
    {
        $response = $this->post('/sms/verify', [
            'messageId' => $messageId,
            'code' => $code,
        ]);

        if ($this->succeeded($response)) {
            return;
        }

        $message = (string) ($response->json('message') ?? $response->json('error') ?? '');

        Log::warning('OTP verify rejected', ['status' => $response->status(), 'body' => $response->json()]);

        throw new OtpException(
            str_contains(strtolower($message), 'expire') ? 'EXPIRED_CODE' : 'INVALID_CODE'
        );
    }

    /**
     * Arqam answers HTTP 200 even when it rejects a request; the body's
     * success flag is the real verdict.
     */
    private function succeeded(Response $response): bool
    {
        if ($response->failed()) {
            return false;
        }

        return $response->json('success') !== false;
    }

    /**
     * A DNS miss, a refused connection or a timeout throws ConnectionException,
     * which would otherwise escape as a raw 500. Arqam being unreachable is
     * exactly what SERVICE_UNAVAILABLE means, so it is turned into that — the
     * customer reads "الخدمة غير متاحة حالياً" and monitoring sees a 503.
     */
    private function post(string $uri, array $body): Response
    {
        try {
            return $this->client()->post($uri, $body);
        } catch (ConnectionException $e) {
            Log::warning('OTP provider unreachable', ['uri' => $uri, 'message' => $e->getMessage()]);

            throw new OtpException('SERVICE_UNAVAILABLE');
        }
    }

    private function client(): PendingRequest
    {
        $baseUrl = $this->settings->get(SettingKey::OTP_BASE_URL);
        $apiKey = $this->settings->get(SettingKey::OTP_API_KEY);

        if (! $baseUrl || ! $apiKey) {
            throw new OtpException('NOT_CONFIGURED');
        }

        // Short on purpose: a stalled provider must not pin a php-fpm worker for
        // half a minute per attempt while the service is down.
        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(8)
            ->connectTimeout(5);
    }

    private function fakeSend(string $phone, OtpPurpose $purpose): string
    {
        Log::info('OTP (fake mode)', [
            'phone' => $phone,
            'purpose' => $purpose->value,
            'code' => self::FAKE_CODE,
        ]);

        return 'fake-'.$purpose->value.'-'.$phone;
    }

    private function fakeVerify(string $code): void
    {
        if ($code !== self::FAKE_CODE) {
            throw new OtpException('INVALID_CODE');
        }
    }
}
