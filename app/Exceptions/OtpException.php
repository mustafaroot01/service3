<?php

namespace App\Exceptions;

use RuntimeException;

class OtpException extends RuntimeException
{
    private const MESSAGES = [
        'INVALID_PHONE' => 'رقم الهاتف غير صحيح',
        'EXPIRED_CODE' => 'انتهت صلاحية الرمز، اطلب رمزاً جديداً',
        'INVALID_CODE' => 'الرمز غير صحيح',
        'COOLDOWN' => 'انتظر قليلاً قبل إعادة إرسال الرمز',
        'TOO_MANY_REQUESTS' => 'حاولت كثيراً، انتظر قليلاً ثم أعد المحاولة',
        'MISSING_API_KEY' => 'خدمة الرسائل غير مهيّأة، راجع الإدارة',
        'INVALID_API_KEY' => 'خدمة الرسائل غير مهيّأة بشكل صحيح، راجع الإدارة',
        'INSUFFICIENT_CREDITS' => 'رصيد الرسائل غير كافٍ، راجع الإدارة',
        'NOT_CONFIGURED' => 'خدمة الرسائل غير مهيّأة، راجع الإدارة',
        'SERVICE_UNAVAILABLE' => 'خدمة الرسائل غير متاحة حالياً، حاول لاحقاً',
    ];

    public readonly string $reason;

    public function __construct(string $reason, ?string $message = null)
    {
        $this->reason = $reason;

        parent::__construct($message ?? self::MESSAGES[$reason] ?? self::MESSAGES['SERVICE_UNAVAILABLE']);
    }

    public static function fromProvider(?string $providerCode, ?string $providerMessage = null): self
    {
        $key = strtoupper((string) $providerCode);

        if (isset(self::MESSAGES[$key])) {
            return new self($key);
        }

        return new self('SERVICE_UNAVAILABLE', $providerMessage
            ? 'خدمة الرسائل ردّت بخطأ: '.$providerMessage
            : null);
    }
}
