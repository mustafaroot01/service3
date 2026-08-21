<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Orders are permanent records, so a category or service that has orders is
 * retired by deactivating it — deleting it is refused with a clean 422, never a
 * raw 500, and never with its image stripped first.
 */
beforeEach(function () {
    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;

    $this->governorate = Governorate::create(['name' => 'بغداد']);
    $this->district = District::create(['governorate_id' => $this->governorate->id, 'name' => 'الكرخ']);
    $this->category = Category::create(['name' => 'تكييف']);
    $this->service = Service::create(['category_id' => $this->category->id, 'name' => 'صيانة مكيّفات']);
    $this->customer = User::factory()->verified()->create([
        'governorate_id' => $this->governorate->id, 'district_id' => $this->district->id,
    ]);

    $this->placeOrder = fn () => Order::createWithNumber([
        'user_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'governorate_id' => $this->governorate->id,
        'district_id' => $this->district->id,
        'description' => 'الجهاز لا يبرّد',
        'scheduled_date' => today(),
        'time_from' => '10:00',
        'time_to' => '11:00',
        'latitude' => 33.3152,
        'longitude' => 44.3661,
    ]);

    $this->deleteService = fn () => $this->actingAs($this->admin, 'admin')
        ->deleteJson("/api/v1/admin/services/{$this->service->id}");
    $this->deleteCategory = fn () => $this->actingAs($this->admin, 'admin')
        ->deleteJson("/api/v1/admin/categories/{$this->category->id}");
});

it('refuses to delete a service that has orders, with a clean message', function () {
    ($this->placeOrder)();

    ($this->deleteService)()
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'لا يمكن حذف خدمة مرتبطة بـ1 طلب. عطّلها بدل الحذف.');

    expect(Service::whereKey($this->service->id)->exists())->toBeTrue();
});

it('deletes a service that has no orders', function () {
    ($this->deleteService)()->assertSuccessful();

    expect(Service::whereKey($this->service->id)->exists())->toBeFalse();
});

it('refuses to delete a category whose service has orders', function () {
    ($this->placeOrder)();

    ($this->deleteCategory)()
        ->assertStatus(422)
        ->assertJsonPath('message', 'لا يمكن حذف قسم مرتبط بطلبات على خدماته. عطّله بدل الحذف.');

    expect(Category::whereKey($this->category->id)->exists())->toBeTrue()
        ->and(Service::whereKey($this->service->id)->exists())->toBeTrue();
});

it('deletes a category with no ordered services', function () {
    ($this->deleteCategory)()->assertSuccessful();

    expect(Category::whereKey($this->category->id)->exists())->toBeFalse();
});

it('keeps the image on disk when a delete is refused', function () {
    Storage::fake('public');
    Storage::disk('public')->put('services/keep.jpg', 'binary');
    $this->service->forceFill(['image' => 'services/keep.jpg'])->save();
    ($this->placeOrder)();

    ($this->deleteService)()->assertStatus(422);

    // The refused delete must not have stripped the image first.
    Storage::disk('public')->assertExists('services/keep.jpg');
});
