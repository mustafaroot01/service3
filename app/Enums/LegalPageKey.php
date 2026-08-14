<?php

namespace App\Enums;

enum LegalPageKey: string
{
    case PRIVACY_POLICY = 'privacy_policy';
    case TERMS_OF_USE = 'terms_of_use';

    public function label(): string
    {
        return match($this) {
            self::PRIVACY_POLICY => 'سياسة الخصوصية',
            self::TERMS_OF_USE => 'شروط الاستخدام',
        };
    }
}
