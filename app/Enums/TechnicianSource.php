<?php

namespace App\Enums;

enum TechnicianSource: string
{
    case MANUAL = 'manual';
    case APPLICATION = 'application';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'من اللوحة',
            self::APPLICATION => 'من استمارة',
        };
    }
}
