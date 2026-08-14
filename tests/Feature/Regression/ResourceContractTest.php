<?php

use App\Http\Resources\Api\Admin\GovernorateResource;
use App\Models\District;
use App\Models\Governorate;

/**
 * `$this->count ?? 0` reported a real zero for counts that were never
 * computed, so the dashboard could not tell "none" from "unknown".
 */
it('omits a count it did not compute rather than reporting zero', function () {
    $governorate = Governorate::create(['name' => 'بغداد']);
    District::create(['governorate_id' => $governorate->id, 'name' => 'الكرخ']);

    $bare = (new GovernorateResource($governorate))->resolve();
    expect($bare)->not->toHaveKey('districts_count');

    $counted = (new GovernorateResource($governorate->loadCount('districts')))->resolve();
    expect($counted['districts_count'])->toBe(1);
});

it('rejects a reorder that points at rows which do not exist', function () {
    $admin = App\Models\Admin::factory()->create();
    $admin->syncRoles(['super-admin']);

    $this->actingAs($admin, 'admin')
        ->postJson('/api/v1/admin/governorates/reorder', [
            'items' => [['id' => 9999, 'sort_order' => 1]],
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});
