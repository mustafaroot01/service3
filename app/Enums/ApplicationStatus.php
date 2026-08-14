<?php

namespace App\Enums;

/**
 * There is no "accepted" state: accepting converts the application into a
 * technician and removes it, so acceptance is an action, never a resting place.
 */
enum ApplicationStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'معلّق',
            self::UNDER_REVIEW => 'قيد المراجعة',
            self::REJECTED => 'تم الرفض',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PENDING => [self::UNDER_REVIEW, self::REJECTED],
            self::UNDER_REVIEW => [self::REJECTED],
            self::REJECTED => [self::UNDER_REVIEW],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }
}
