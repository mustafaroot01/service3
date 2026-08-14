<?php

namespace App\Support;

class Phone
{
    private const COUNTRY_CODE = '964';

    public static function international(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, self::COUNTRY_CODE)) {
            return $digits;
        }

        return self::COUNTRY_CODE.ltrim($digits, '0');
    }

    public static function whatsapp(?string $phone, string $message = ''): ?string
    {
        $number = self::international($phone);

        if (! $number) {
            return null;
        }

        $url = "https://wa.me/{$number}";

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }
}
