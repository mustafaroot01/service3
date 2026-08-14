<?php

namespace App\Enums;

enum PermissionModule: string
{
    case GOVERNORATES = 'governorates';
    case DISTRICTS = 'districts';
    case CATEGORIES = 'categories';
    case SERVICES = 'services';
    case SPECIALIZATIONS = 'specializations';
    case SLIDERS = 'sliders';
    case BLOG = 'blog';
    case LEGAL_PAGES = 'legal-pages';
    case TECHNICIANS = 'technicians';
    case TECHNICIAN_APPLICATIONS = 'technician-applications';
    case USERS = 'users';
    case ORDERS = 'orders';
    case NOTIFICATIONS = 'notifications';
    case SETTINGS = 'settings';
    case ADMINS = 'admins';

    public function label(): string
    {
        return match ($this) {
            self::GOVERNORATES => 'المحافظات',
            self::DISTRICTS => 'الأقضية',
            self::CATEGORIES => 'الأقسام',
            self::SERVICES => 'الخدمات',
            self::SLIDERS => 'السلايدرات',
            self::SPECIALIZATIONS => 'الاختصاصات',
            self::BLOG => 'المدوّنة',
            self::LEGAL_PAGES => 'الصفحات القانونية',
            self::TECHNICIANS => 'الفنيون',
            self::TECHNICIAN_APPLICATIONS => 'استمارات الفنيين',
            self::USERS => 'الزبائن',
            self::ORDERS => 'الطلبات',
            self::NOTIFICATIONS => 'الإشعارات',
            self::SETTINGS => 'الإعدادات',
            self::ADMINS => 'المشرفون والأدوار',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::GOVERNORATES, self::DISTRICTS => 'المواقع',
            self::CATEGORIES, self::SERVICES, self::SPECIALIZATIONS => 'الكتالوج',
            self::SLIDERS, self::BLOG, self::LEGAL_PAGES => 'المحتوى',
            self::TECHNICIANS, self::TECHNICIAN_APPLICATIONS, self::USERS => 'الأشخاص',
            self::ORDERS, self::NOTIFICATIONS => 'العمليات',
            self::SETTINGS, self::ADMINS => 'النظام',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
