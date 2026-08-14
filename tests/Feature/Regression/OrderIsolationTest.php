<?php

use App\Models\Category;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;

/**
 * Every customer-facing order query is scoped to the owner. Route model
 * binding alone would expose another customer's home coordinates.
 */
beforeEach(function () {
    $governorate = Governorate::create(['name' => 'بغداد']);
    $district = District::create(['governorate_id' => $governorate->id, 'name' => 'الكرخ']);
    $category = Category::create(['name' => 'تكييف']);
    $this->service = Service::create(['category_id' => $category->id, 'name' => 'صيانة']);

    $this->victim = User::factory()->verified()->create([
        'governorate_id' => $governorate->id, 'district_id' => $district->id,
    ]);
    $this->attacker = User::factory()->verified()->create([
        'governorate_id' => $governorate->id, 'district_id' => $district->id,
    ]);

    $this->order = Order::createWithNumber([
        'user_id' => $this->victim->id,
        'service_id' => $this->service->id,
        'governorate_id' => $governorate->id,
        'district_id' => $district->id,
        'description' => 'سري',
        'scheduled_date' => today(),
        'time_from' => '10:00',
        'time_to' => '11:00',
        'latitude' => 33.3152,
        'longitude' => 44.3661,
    ]);
});

it('hides another customer order from reads', function () {
    $this->actingAs($this->attacker, 'user')
        ->getJson("/api/v1/customer/orders/{$this->order->id}")
        ->assertNotFound();
});

it('hides another customer order from cancellation', function () {
    $this->actingAs($this->attacker, 'user')
        ->postJson("/api/v1/customer/orders/{$this->order->id}/cancel")
        ->assertNotFound();

    expect($this->order->fresh()->status->value)->toBe('pending');
});

it('keeps another customer order out of the list', function () {
    $this->actingAs($this->attacker, 'user')
        ->getJson('/api/v1/customer/orders')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('lets the owner read their own order', function () {
    $this->actingAs($this->victim, 'user')
        ->getJson("/api/v1/customer/orders/{$this->order->id}")
        ->assertOk()
        ->assertJsonPath('data.order_number', $this->order->order_number);
});

it('never leaks the acting staff identity in the customer timeline', function () {
    $this->actingAs($this->victim, 'user')
        ->getJson("/api/v1/customer/orders/{$this->order->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.timeline.0.actor_name')
        ->assertJsonMissingPath('data.timeline.0.actor_id');
});
