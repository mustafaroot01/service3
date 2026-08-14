<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case ASSIGNED = 'assigned';
    case INSPECTED = 'inspected';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'معلّق',
            self::CONFIRMED => 'مؤكّد',
            self::ASSIGNED => 'تم تعيين فني',
            self::INSPECTED => 'تم الكشف',
            self::COMPLETED => 'تم إنجاز الخدمة',
            self::CANCELLED => 'ملغى',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PENDING => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED => [self::ASSIGNED, self::CANCELLED],
            self::ASSIGNED => [self::INSPECTED, self::CANCELLED],
            self::INSPECTED => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED, self::CANCELLED => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    public function isCancellableByCustomer(): bool
    {
        return $this === self::PENDING;
    }
}
