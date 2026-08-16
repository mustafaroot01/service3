<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class OtpException extends RuntimeException
{
    private const MESSAGES = [
        'INVALID_PHONE' => 'رقم الهاتف غير صحيح',
        'EXPIRED_CODE' => 'انتهت صلاحية الرمز، اطلب رمزاً جديداً',
        'INVALID_CODE' => 'الرمز غير صحيح',
        'COOLDOWN' => 'انتظر قليلاً قبل إعادة إرسال الرمز',
        'TOO_MANY_REQUESTS' => 'حاولت كثيراً، انتظر قليلاً ثم أعد المحاولة',
        'INVALID_OTP_FORMAT' => 'الرمز يتكوّن من ٦ أرقام',
        'MISSING_API_KEY' => 'خدمة الرسائل غير مهيّأة، راجع الإدارة',
        'INVALID_API_KEY' => 'خدمة الرسائل غير مهيّأة بشكل صحيح، راجع الإدارة',
        'INSUFFICIENT_CREDITS' => 'رصيد الرسائل غير كافٍ، راجع الإدارة',
        'NOT_CONFIGURED' => 'خدمة الرسائل غير مهيّأة، راجع الإدارة',
        'SERVICE_UNAVAILABLE' => 'خدمة الرسائل غير متاحة حالياً، حاول لاحقاً',
    ];

    /**
     * Faults on the messaging service's side, not the caller's: an unconfigured
     * key, a drained balance, the host unreachable. These are a 503 the app
     * shows as "try later" and monitoring counts as an outage — never a 422
     * that reddens the code field. Anything the provider reports that we do not
     * map (its own address throttling included) collapses to SERVICE_UNAVAILABLE.
     */
    private const SERVICE_REASONS = [
        'NOT_CONFIGURED', 'MISSING_API_KEY', 'INVALID_API_KEY',
        'INSUFFICIENT_CREDITS', 'SERVICE_UNAVAILABLE', 'TOO_MANY_REQUESTS',
    ];

    public readonly string $reason;

    public function __construct(string $reason, ?string $message = null)
    {
        $this->reason = $reason;

        parent::__construct($message ?? self::MESSAGES[$reason] ?? self::MESSAGES['SERVICE_UNAVAILABLE']);
    }

    public function isServiceFault(): bool
    {
        return \in_array($this->reason, self::SERVICE_REASONS, true);
    }

    public function status(): int
    {
        return $this->isServiceFault() ? 503 : 422;
    }

    /**
     * The provider answers in English — "IP is blocked", "Rate limit exceeded" —
     * and that text is meaningless to a customer waiting on a code. An unknown
     * code becomes the generic Arabic line; the provider's own words go to the
     * log, where whoever has to fix it will look.
     */
    public static function fromProvider(?string $providerCode, ?string $providerMessage = null): self
    {
        $key = strtoupper((string) $providerCode);

        if (isset(self::MESSAGES[$key])) {
            return new self($key);
        }

        Log::warning('OTP provider returned an unmapped failure', [
            'code' => $providerCode,
            'message' => $providerMessage,
        ]);

        return new self('SERVICE_UNAVAILABLE');
    }
}
