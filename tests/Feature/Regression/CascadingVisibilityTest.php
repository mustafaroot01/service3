<?php

use App\Models\Category;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Service;

beforeEach(function () {
    $this->governorate = Governorate::create(['name' => 'بغداد']);
    $this->district = District::create(['governorate_id' => $this->governorate->id, 'name' => 'الكرخ']);
    $this->category = Category::create(['name' => 'تكييف']);
    $this->service = Service::create(['category_id' => $this->category->id, 'name' => 'صيانة']);
});

it('hides districts of a hidden governorate without touching their own flag', function () {
    $this->governorate->update(['is_active' => false]);

    expect(District::visible()->count())->toBe(0)
        ->and($this->district->fresh()->is_active)->toBeTrue();

    $this->getJson("/api/v1/customer/governorates/{$this->governorate->id}/districts")->assertNotFound();
});

it('hides services of a hidden category, including by direct link', function () {
    $this->category->update(['is_active' => false]);

    expect(Service::visible()->count())->toBe(0);

    $this->getJson("/api/v1/customer/services/{$this->service->id}")->assertNotFound();
    $this->getJson('/api/v1/customer/services')->assertOk()->assertJsonPath('meta.total', 0);
});

it('restores visibility when the parent comes back', function () {
    $this->governorate->update(['is_active' => false]);
    $this->governorate->update(['is_active' => true]);

    expect(District::visible()->count())->toBe(1);
});
