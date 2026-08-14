<?php

namespace App\Enums;

enum ActorType: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'الإدارة',
            self::USER => 'الزبون',
            self::SYSTEM => 'النظام',
        };
    }
}
