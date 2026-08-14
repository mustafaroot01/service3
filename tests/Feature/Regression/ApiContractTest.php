<?php

use App\Models\Admin;
use App\Models\User;

it('versions every api route under v1', function () {
    $unversioned = collect(Route::getRoutes())
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri) => str_starts_with($uri, 'api/') && ! str_starts_with($uri, 'api/v1/'))
        ->values();

    expect($unversioned)->toBeEmpty();
});

it('answers unauthenticated api calls with the standard envelope', function () {
    $this->getJson('/api/v1/admin/orders')
        ->assertUnauthorized()
        ->assertJson(['success' => false, 'message' => 'Unauthorized']);
});

it('answers a missing record with the standard envelope', function () {
    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);

    $this->actingAs($admin, 'admin')
        ->getJson('/api/v1/admin/governorates/9999')
        ->assertNotFound()
        ->assertJson(['success' => false]);
});

it('keeps the customer guard out of the admin panel', function () {
    $this->actingAs(User::factory()->verified()->create(), 'user')
        ->getJson('/api/v1/admin/orders')
        ->assertUnauthorized();
});

it('guards every admin route with a permission except the auth endpoints', function () {
    $unguarded = collect(Route::getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/'))
        ->reject(fn ($route) => str_contains($route->uri(), '/auth/'))
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:')))
        ->map(fn ($route) => $route->uri())
        ->values();

    expect($unguarded)->toBeEmpty();
});
