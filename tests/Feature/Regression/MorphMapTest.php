<?php

use App\Models\Admin;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * enforceMorphMap() applies to every polymorphic relation in the app,
 * including Sanctum's tokenable and Spatie's model_type. A missing entry
 * broke admin login outright.
 */
it('maps every model that takes part in a polymorphic relation', function () {
    foreach ([Admin::class, User::class, Technician::class] as $model) {
        expect(Relation::getMorphAlias($model))->not->toBe($model);
    }
});

it('lets an admin issue a token and keep its roles', function () {
    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);

    expect($admin->createToken('t')->plainTextToken)->toBeString()
        ->and($admin->fresh()->roles->pluck('name')->all())->toBe(['super-admin'])
        ->and($admin->getAllPermissions())->not->toBeEmpty();
});

it('stores short aliases rather than class names', function () {
    $admin = Admin::factory()->create();
    $admin->createToken('t');

    expect(DB::table('personal_access_tokens')->value('tokenable_type'))->toBe('admin');
});
