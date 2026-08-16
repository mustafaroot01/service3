<?php

namespace App\Enums;

enum SettingKey: string
{
    case OTP_BASE_URL = 'otp_base_url';
    case OTP_API_KEY = 'otp_api_key';
    case ONESIGNAL_APP_ID = 'onesignal_app_id';
    case ONESIGNAL_REST_API_KEY = 'onesignal_rest_api_key';
    case TECHNICIAN_APPLICATION_OPEN = 'technician_application_open';

    public function label(): string
    {
        return match ($this) {
            self::OTP_BASE_URL => 'رابط خدمة أرقم',
            self::OTP_API_KEY => 'مفتاح أرقم (OTP)',
            self::ONESIGNAL_APP_ID => 'معرّف تطبيق OneSignal',
            self::ONESIGNAL_REST_API_KEY => 'مفتاح OneSignal REST',
            self::TECHNICIAN_APPLICATION_OPEN => 'استقبال استمارات الفنيين',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::OTP_BASE_URL, self::OTP_API_KEY => 'otp',
            self::ONESIGNAL_APP_ID, self::ONESIGNAL_REST_API_KEY => 'onesignal',
            self::TECHNICIAN_APPLICATION_OPEN => 'technician_application',
        };
    }

    public function groupLabel(): string
    {
        return match ($this->group()) {
            'otp' => 'إعدادات الرسائل (أرقم)',
            'onesignal' => 'إعدادات الإشعارات (OneSignal)',
            'technician_application' => 'استمارة انضمام الفنيين',
        };
    }

    /** How the panel renders the field: a text box, a hidden secret, or a switch. */
    public function type(): string
    {
        return match ($this) {
            self::OTP_API_KEY, self::ONESIGNAL_REST_API_KEY => 'secret',
            self::TECHNICIAN_APPLICATION_OPEN => 'boolean',
            self::OTP_BASE_URL, self::ONESIGNAL_APP_ID => 'text',
        };
    }

    public function isSecret(): bool
    {
        return $this->type() === 'secret';
    }

    /** A switch has to answer even when nothing was ever saved. */
    public function default(): ?string
    {
        return $this === self::TECHNICIAN_APPLICATION_OPEN ? '1' : null;
    }

    public function hint(): ?string
    {
        return match ($this) {
            self::TECHNICIAN_APPLICATION_OPEN => 'عند الإطفاء تختفي الاستمارة من التطبيق ويُرفض أي إرسال',
            // Dropping the /api gives a 405 page and every code stops silently.
            self::OTP_BASE_URL => 'https://otp.arqam.tech/api — لا تحذف /api من آخر الرابط',
            self::OTP_API_KEY => 'من لوحة أرقم. يبدأ بـ otplive_ ويُخزَّن مشفّراً',
            default => null,
        };
    }

    public function envKey(): string
    {
        return strtoupper($this->value);
    }

    /**
     * @return array<string, array<int, self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $key) {
            $groups[$key->group()][] = $key;
        }

        return $groups;
    }
}
