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
        // The media endpoint cannot use a permission gate: an <img> tag carries
        // no bearer token, so a short-lived signature is its credential instead.
        ->reject(fn ($route) => $route->uri() === 'api/v1/admin/media')
        ->reject(fn ($route) => collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'permission:')))
        ->map(fn ($route) => $route->uri())
        ->values();

    expect($unguarded)->toBeEmpty();
});

it('locks the media endpoint behind a valid signature', function () {
    $route = collect(Route::getRoutes())
        ->first(fn ($r) => $r->uri() === 'api/v1/admin/media');

    expect(collect($route->gatherMiddleware())->contains('signed'))->toBeTrue();

    // Unsigned, it is turned away before it can read anything.
    $this->getJson('/api/v1/admin/media?path=applications/1/x.png')->assertStatus(403);
});
