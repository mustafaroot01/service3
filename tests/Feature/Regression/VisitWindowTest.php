<?php

use App\Support\VisitWindow;
use Illuminate\Support\Carbon;

/**
 * The clock is a continuous 24 hours: the visit has no minimum length and may
 * run past midnight into the next day. Only a zero-length range or a start that
 * has already passed is refused.
 */
beforeEach(fn () => Carbon::setTestNow(Carbon::parse('2026-08-13 08:00:00')));

afterEach(fn () => Carbon::setTestNow());

it('accepts any range with free minutes', function (string $from, string $to) {
    expect(VisitWindow::problemWith($from, $to))->toBeNull();
})->with([
    ['09:13', '10:13'],
    ['19:47', '20:47'],
    ['08:05', '23:30'],
    ['22:00', '23:00'],
]);

it('accepts a range far shorter than an hour', function (string $from, string $to) {
    expect(VisitWindow::problemWith($from, $to))->toBeNull();
})->with([
    ['10:00', '10:30'],
    ['10:00', '10:05'],
    ['10:00', '10:01'],
]);

it('carries a range that ends before it starts into the next day', function () {
    expect(VisitWindow::problemWith('23:30', '00:30'))->toBeNull()
        ->and(VisitWindow::end('23:30', '00:30')->toDateTimeString())->toBe('2026-08-14 00:30:00')
        ->and(VisitWindow::crossesMidnight('23:30', '00:30'))->toBeTrue();
});

it('keeps a range that ends the same day on the order day', function () {
    expect(VisitWindow::end('09:00', '10:00')->toDateTimeString())->toBe('2026-08-13 10:00:00')
        ->and(VisitWindow::crossesMidnight('09:00', '10:00'))->toBeFalse();
});

it('rejects a range with no length at all', function () {
    expect(VisitWindow::problemWith('22:00', '22:00'))->toBe('same')
        ->and(VisitWindow::end('22:00', '22:00'))->toBeNull();
});

it('rejects a time that has already passed', function () {
    expect(VisitWindow::problemWith('06:00', '07:00'))->toBe('past');
});

it('always schedules the visit on the order day', function () {
    expect(VisitWindow::date()->toDateString())->toBe('2026-08-13');
});

it('keeps taking orders in the last hour of the day', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 23:30:00'));

    expect(VisitWindow::isOpen())->toBeTrue()
        ->and(VisitWindow::earliestStart()->format('H:i'))->toBe('23:31')
        ->and(VisitWindow::latestStart()->format('H:i'))->toBe('23:59')
        ->and(VisitWindow::problemWith('23:45', '01:15'))->toBeNull();
});

it('closes only once no minute of the day is left', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-13 23:59:10'));

    expect(VisitWindow::isOpen())->toBeFalse()
        ->and(VisitWindow::earliestStart())->toBeNull();
});
