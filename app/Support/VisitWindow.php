<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class VisitWindow
{
    /**
     * The visit is always on the day the order is placed, so the customer picks
     * a time range only — never a date.
     */
    public static function date(): Carbon
    {
        return Carbon::today();
    }

    public static function earliestStart(): ?Carbon
    {
        $now = Carbon::now()->addMinute()->seconds(0);

        return $now->gt(self::latestStart()) ? null : $now;
    }

    /** The visit may run into the next day, so any minute of today can start it. */
    public static function latestStart(): Carbon
    {
        return self::date()->endOfDay()->seconds(0);
    }

    public static function isOpen(): bool
    {
        return self::earliestStart() !== null;
    }

    public static function start(string $from): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', self::date()->toDateString().' '.$from);
    }

    /**
     * The clock runs a full 24 hours: an end earlier than the start belongs to
     * the next day. An end equal to the start has no length, so it has no date.
     */
    public static function end(string $from, string $to): ?Carbon
    {
        $start = self::start($from);
        $end = Carbon::createFromFormat('Y-m-d H:i', self::date()->toDateString().' '.$to);

        if ($end->equalTo($start)) {
            return null;
        }

        return $end->lt($start) ? $end->addDay() : $end;
    }

    public static function crossesMidnight(string $from, string $to): bool
    {
        return self::end($from, $to)?->gt(self::date()->endOfDay()) ?? false;
    }

    /**
     * Returns an error key, or null when the range is acceptable.
     */
    public static function problemWith(string $from, string $to): ?string
    {
        if (self::end($from, $to) === null) {
            return 'same';
        }

        if (self::start($from)->isPast()) {
            return 'past';
        }

        return null;
    }

    public static function label(Carbon $from, Carbon $to): string
    {
        return self::clock($from).' '.self::period($from).' - '.self::clock($to).' '.self::period($to);
    }

    public static function period(Carbon $time): string
    {
        return match (true) {
            $time->hour < 12 => 'صباحاً',
            $time->hour < 17 => 'ظهراً',
            $time->hour < 20 => 'مساءً',
            default => 'ليلاً',
        };
    }

    private static function clock(Carbon $time): string
    {
        return ($time->hour % 12 ?: 12).':'.$time->format('i');
    }
}
