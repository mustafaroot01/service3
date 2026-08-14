<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case REGISTER = 'register';
    case RESET = 'reset';

    public function label(): string
    {
        return match ($this) {
            self::REGISTER => 'تأكيد التسجيل',
            self::RESET => 'استعادة كلمة السر',
        };
    }

    public function endpoint(): string
    {
        return match ($this) {
            self::REGISTER => '/sms/otp',
            self::RESET => '/sms/reset-password',
        };
    }
}
