<?php

namespace App\Enums;

enum PermissionAction: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'عرض',
            self::CREATE => 'إضافة',
            self::UPDATE => 'تعديل',
            self::DELETE => 'حذف',
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
