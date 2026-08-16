<?php

use App\Enums\TechnicianStatus;
use App\Models\Admin;
use App\Models\Category;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Service;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Orders point back at the technician with nullOnDelete, so deleting one who
 * has any would strip his name off every order he handled — the in-progress
 * ones included. He is disabled through his status, never deleted.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->governorate = Governorate::create(['name' => 'بغداد', 'is_active' => true]);
    $this->district = District::create([
        'governorate_id' => $this->governorate->id, 'name' => 'الكرخ', 'is_active' => true,
    ]);
    $category = Category::create(['name' => 'تكييف']);
    $this->service = Service::create(['category_id' => $category->id, 'name' => 'صيانة']);

    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;

    $this->technician = Technician::create([
        'name' => 'حيدر كاظم', 'phone' => '9647712345678',
        'governorate_id' => $this->governorate->id, 'district_id' => $this->district->id,
        'status' => TechnicianStatus::ACTIVE,
    ]);
});

function orderFor(int $technicianId): Order
{
    $customer = User::factory()->verified()->create([
        'governorate_id' => test()->governorate->id, 'district_id' => test()->district->id,
    ]);

    return Order::createWithNumber([
        'user_id' => $customer->id,
        'service_id' => test()->service->id,
        'technician_id' => $technicianId,
        'governorate_id' => test()->governorate->id,
        'district_id' => test()->district->id,
        'description' => 'صيانة مكيّف',
        'scheduled_date' => today(),
        'time_from' => '10:00',
        'time_to' => '11:00',
        'latitude' => 33.3152,
        'longitude' => 44.3661,
    ]);
}

it('refuses to delete a technician who has orders', function () {
    orderFor($this->technician->id);

    $this->actingAs($this->admin, 'admin')
        ->deleteJson("/api/v1/admin/technicians/{$this->technician->id}")
        ->assertStatus(422)
        ->assertJsonPath('errors.technician.0', 'لا يمكن حذف فني لديه طلبات، أوقفه من حالته بدل الحذف');

    expect(Technician::find($this->technician->id))->not->toBeNull();
});

it('deletes a technician who never took an order', function () {
    $this->actingAs($this->admin, 'admin')
        ->deleteJson("/api/v1/admin/technicians/{$this->technician->id}")
        ->assertOk();

    expect(Technician::find($this->technician->id))->toBeNull();
});

it('lets an admin disable a technician who has orders instead', function () {
    orderFor($this->technician->id);

    $this->actingAs($this->admin, 'admin')
        ->patchJson("/api/v1/admin/technicians/{$this->technician->id}/status", ['status' => 'suspended'])
        ->assertOk();

    expect($this->technician->fresh()->status)->toBe(TechnicianStatus::SUSPENDED);
});
