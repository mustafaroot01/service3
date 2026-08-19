<?php

use App\Enums\OrderStatus;
use App\Enums\TechnicianStatus;
use App\Models\Admin;
use App\Models\Category;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Service;
use App\Models\Technician;
use App\Models\User;

/**
 * Replacing a technician leaves the order's status alone. Reporting the swap
 * as a move to "assigned" used to put a status the order is not in into the
 * notification payload, and left the replaced technician expecting the job.
 */
beforeEach(function () {
    $this->governorate = Governorate::create(['name' => 'بغداد']);
    $other = Governorate::create(['name' => 'البصرة']);
    $district = District::create(['governorate_id' => $this->governorate->id, 'name' => 'الكرخ']);
    $otherDistrict = District::create(['governorate_id' => $other->id, 'name' => 'الزبير']);
    $category = Category::create(['name' => 'تكييف']);
    $service = Service::create(['category_id' => $category->id, 'name' => 'صيانة']);

    $this->customer = User::factory()->verified()->create([
        'governorate_id' => $this->governorate->id, 'district_id' => $district->id,
    ]);

    $serial = 0;
    $make = function (string $name, District $in, TechnicianStatus $status = TechnicianStatus::ACTIVE) use (&$serial) {
        return Technician::create([
            'name' => $name,
            'phone' => '077100000'.str_pad((string) ++$serial, 2, '0', STR_PAD_LEFT),
            'governorate_id' => $in->governorate_id,
            'district_id' => $in->id,
            'status' => $status,
        ]);
    };

    $this->first = $make('علي', $district);
    $this->second = $make('أحمد', $district);
    $this->outsider = $make('كريم', $otherDistrict);
    $this->inactive = $make('سعد', $district, TechnicianStatus::PENDING);

    $this->order = Order::createWithNumber([
        'user_id' => $this->customer->id,
        'service_id' => $service->id,
        'governorate_id' => $this->governorate->id,
        'district_id' => $district->id,
        'technician_id' => $this->first->id,
        'description' => 'تبريد',
        'scheduled_date' => today(),
        'time_from' => '10:00',
        'time_to' => '11:00',
        'latitude' => 33.3152,
        'longitude' => 44.3661,
    ]);

    $this->order->forceFill(['status' => OrderStatus::INSPECTED])->save();

    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;
});

$swap = fn ($test, int $id) => $test->actingAs($test->admin, 'admin')
    ->postJson("/api/v1/admin/orders/{$test->order->id}/reassign-technician", ['technician_id' => $id]);

it('swaps the technician without moving the order out of its status', function () use ($swap) {
    $swap($this, $this->second->id)
        ->assertOk()
        ->assertJsonPath('data.technician.id', $this->second->id)
        ->assertJsonPath('data.status', OrderStatus::INSPECTED->value);

    expect($this->order->fresh()->status)->toBe(OrderStatus::INSPECTED);
});

it('never reports a status the order is not in', function () use ($swap) {
    $swap($this, $this->second->id)->assertOk();

    $reported = Notification::pluck('data')->map(fn ($d) => $d['to_status'])->unique();

    expect($reported->all())->toBe([OrderStatus::INSPECTED->value]);
});

it('tells the customer, the new technician and the one who was replaced', function () use ($swap) {
    $swap($this, $this->second->id)->assertOk();

    $recipients = Notification::get()->map(fn ($n) => $n->notifiable_type.':'.$n->notifiable_id)->sort()->values();

    expect($recipients->all())->toBe(collect([
        "user:{$this->customer->id}",
        "technician:{$this->second->id}",
        "technician:{$this->first->id}",
    ])->sort()->values()->all());
});

it('records the swap in the audit trail without inventing a transition', function () use ($swap) {
    $swap($this, $this->second->id)->assertOk();

    $entry = $this->order->statusHistories()->latest('id')->first();

    expect($entry->from_status)->toBe(OrderStatus::INSPECTED)
        ->and($entry->to_status)->toBe(OrderStatus::INSPECTED)
        ->and($entry->note)->toBe('تم استبدال الفني: علي ← أحمد');
});

it('refuses a technician from another governorate', function () use ($swap) {
    $swap($this, $this->outsider->id)->assertStatus(422)
        ->assertJsonPath('errors.technician_id.0', 'يجب اختيار فني نشط من نفس محافظة الطلب');

    expect($this->order->fresh()->technician_id)->toBe($this->first->id);
});

it('refuses a technician who is not active', function () use ($swap) {
    $swap($this, $this->inactive->id)->assertStatus(422);

    expect($this->order->fresh()->technician_id)->toBe($this->first->id);
});

it('refuses to swap in the technician who is already on the order', function () use ($swap) {
    $swap($this, $this->first->id)->assertStatus(422)
        ->assertJsonPath('errors.technician_id.0', 'هذا الفني معيَّن على الطلب بالفعل');
});

it('refuses to swap on an order that has reached a final status', function () use ($swap) {
    $this->order->forceFill(['status' => OrderStatus::COMPLETED])->save();

    $swap($this, $this->second->id)->assertStatus(422);

    expect($this->order->fresh()->technician_id)->toBe($this->first->id);
});

it('refuses to swap when no technician has been assigned yet', function () use ($swap) {
    $this->order->forceFill(['technician_id' => null, 'status' => OrderStatus::CONFIRMED])->save();

    $swap($this, $this->second->id)->assertStatus(422)
        ->assertJsonPath('errors.technician_id.0', 'لم يُعيَّن فني بعد، استخدم تعيين فني');
});

it('writes nothing at all when a guard rejects the swap', function () use ($swap) {
    $historyBefore = $this->order->statusHistories()->count();

    $swap($this, $this->outsider->id)->assertStatus(422);

    expect($this->order->statusHistories()->count())->toBe($historyBefore)
        ->and(Notification::count())->toBe(0);
});

it('completes the swap even when the push transport fails, keeping the in-app rows', function () use ($swap) {
    // Notifications run inside the request now; a dead OneSignal must not break
    // the reassignment, and the in-app record must survive the push failure.
    $this->mock(App\Services\PushService::class, function ($mock) {
        $mock->shouldReceive('send')->andThrow(new RuntimeException('OneSignal unreachable'));
    });

    $swap($this, $this->second->id)->assertOk();

    expect($this->order->fresh()->technician_id)->toBe($this->second->id)
        ->and(Notification::where('type', 'technician_reassigned')->count())->toBe(3);
});
