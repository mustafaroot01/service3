<?php

use App\Models\Category;
use App\Models\Service;

/**
 * The app's search box: a customer types letters and gets service suggestions,
 * matched the way Arabic is actually typed — without worrying about hamza forms,
 * ta-marbuta, or diacritics — and ranked so the closest name comes first.
 */
$suggest = fn ($test, string $q, array $params = []) => $test->getJson(
    '/api/v1/customer/services/suggest?'.http_build_query(['q' => $q] + $params)
);

beforeEach(function () {
    $this->category = Category::create(['name' => 'تكييف وتبريد']);
});

it('suggests a visible service by its name', function () use ($suggest) {
    Service::create(['category_id' => $this->category->id, 'name' => 'صيانة مكيّفات']);

    $suggest($this, 'صيانة')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'صيانة مكيّفات')
        ->assertJsonPath('data.0.category.name', 'تكييف وتبريد');
});

it('ignores ta-marbuta and diacritics when matching', function () use ($suggest) {
    Service::create(['category_id' => $this->category->id, 'name' => 'صيانة مكيّفات']);

    // Typed with a plain ه and no shadda — still finds «صيانة مكيّفات».
    $suggest($this, 'صيانه مكيفات')->assertOk()->assertJsonCount(1, 'data');
});

it('ignores hamza shape when matching', function () use ($suggest) {
    Service::create(['category_id' => $this->category->id, 'name' => 'تركيب أفران']);

    // Bare alef in the query matches the hamza-on-alef in the name.
    $suggest($this, 'افران')->assertOk()->assertJsonPath('data.0.name', 'تركيب أفران');
});

it('ranks a name that starts with the term ahead of one that merely contains it', function () use ($suggest) {
    Service::create(['category_id' => $this->category->id, 'name' => 'صيانة تكييف']);
    Service::create(['category_id' => $this->category->id, 'name' => 'تكييف مركزي']);

    $suggest($this, 'تكييف')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'تكييف مركزي');
});

it('surfaces a service by its category name', function () use ($suggest) {
    $plumbing = Category::create(['name' => 'السباكة']);
    Service::create(['category_id' => $plumbing->id, 'name' => 'تسليك مجاري']);

    // The service name has no «سباكة», but its category does.
    $suggest($this, 'سباكه')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'تسليك مجاري');
});

it('never suggests an inactive service', function () use ($suggest) {
    Service::create(['category_id' => $this->category->id, 'name' => 'صيانة مكيّفات', 'is_active' => false]);

    $suggest($this, 'صيانة')->assertOk()->assertJsonCount(0, 'data');
});

it('never suggests a service under an inactive category', function () use ($suggest) {
    $hidden = Category::create(['name' => 'كهرباء', 'is_active' => false]);
    Service::create(['category_id' => $hidden->id, 'name' => 'صيانة كهرباء']);

    $suggest($this, 'صيانة')->assertOk()->assertJsonCount(0, 'data');
});

it('caps the number of suggestions at the requested limit', function () use ($suggest) {
    foreach (range(1, 12) as $i) {
        Service::create(['category_id' => $this->category->id, 'name' => "خدمة رقم {$i}"]);
    }

    $suggest($this, 'خدمة', ['limit' => 5])->assertOk()->assertJsonCount(5, 'data');
});

it('returns an empty list for a blank query instead of the whole catalogue', function () use ($suggest) {
    Service::create(['category_id' => $this->category->id, 'name' => 'صيانة مكيّفات']);

    $suggest($this, '   ')->assertOk()->assertJsonCount(0, 'data');
});
