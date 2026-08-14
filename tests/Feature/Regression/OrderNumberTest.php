<?php

use App\Models\Category;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * MySQL runs on REPEATABLE READ, so a plain read of the last order number
 * returns the snapshot the transaction opened with. The retry loop then
 * recomputed the same number forever and the customer's order failed.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-14 10:00:00'));

    $governorate = Governorate::create(['name' => 'بغداد']);
    $district = District::create(['governorate_id' => $governorate->id, 'name' => 'الكرخ']);
    $category = Category::create(['name' => 'كهرباء']);
    $service = Service::create(['category_id' => $category->id, 'name' => 'صيانة']);
    $user = User::factory()->verified()->create([
        'governorate_id' => $governorate->id, 'district_id' => $district->id,
    ]);

    $this->attributes = [
        'user_id' => $user->id,
        'service_id' => $service->id,
        'governorate_id' => $governorate->id,
        'district_id' => $district->id,
        'description' => 'وصف',
        'scheduled_date' => today(),
        'time_from' => '10:00',
        'time_to' => '11:00',
        'latitude' => 33.31,
        'longitude' => 44.36,
    ];
});

afterEach(fn () => Carbon::setTestNow());

it('numbers the orders of a day in sequence', function () {
    $numbers = collect(range(1, 5))->map(fn () => Order::createWithNumber($this->attributes)->order_number);

    expect($numbers->all())->toBe([
        'HS-260814-0001', 'HS-260814-0002', 'HS-260814-0003', 'HS-260814-0004', 'HS-260814-0005',
    ]);
});

it('starts a new day at one', function () {
    Order::createWithNumber($this->attributes);

    Carbon::setTestNow(Carbon::parse('2026-08-15 09:00:00'));

    expect(Order::createWithNumber($this->attributes)->order_number)->toBe('HS-260815-0001');
});

it('keeps counting past the four digit mark instead of wrapping to one', function () {
    Order::createWithNumber($this->attributes)->forceFill(['order_number' => 'HS-260814-9999'])->save();

    expect(Order::nextNumber())->toBe('HS-260814-10000');

    Order::createWithNumber($this->attributes)->forceFill(['order_number' => 'HS-260814-10000'])->save();

    expect(Order::nextNumber())->toBe('HS-260814-10001');
});

it('reads the counter under a lock so a concurrent order cannot be missed', function () {
    DB::enableQueryLog();
    Order::nextNumber();

    expect(collect(DB::getQueryLog())->pluck('query')->implode(' '))->toContain('for update');
});

it('never issues the same number twice', function () {
    collect(range(1, 20))->each(fn () => Order::createWithNumber($this->attributes));

    $numbers = Order::pluck('order_number');

    expect($numbers)->toHaveCount(20)
        ->and($numbers->unique())->toHaveCount(20);
});

it('flags a visit that runs past midnight', function () {
    $order = Order::createWithNumber($this->attributes + []);
    $order->forceFill(['time_from' => '23:30', 'time_to' => '00:30'])->save();

    expect($order->fresh()->visitEndsNextDay())->toBeTrue();

    $order->forceFill(['time_from' => '09:00', 'time_to' => '10:00'])->save();

    expect($order->fresh()->visitEndsNextDay())->toBeFalse();
});
