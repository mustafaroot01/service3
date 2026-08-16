<?php

use App\Models\BlogPost;
use App\Models\User;

/**
 * A page size is caller input. Left unclamped, a negative value makes MySQL
 * drop the LIMIT and stream the whole table; the four public list endpoints
 * used to pass it straight through, unlike the admin side.
 */
beforeEach(function () {
    foreach (range(1, 5) as $i) {
        BlogPost::create(['title' => "مقال {$i}", 'content' => 'x', 'published_at' => now(), 'is_active' => true]);
    }
});

it('clamps per_page on the public blog endpoint', function (int|string $value, int $expected) {
    $this->getJson("/api/v1/customer/blog?per_page={$value}")
        ->assertOk()
        ->assertJsonPath('meta.per_page', $expected);
})->with([
    'negative kills the LIMIT' => [-1, 1],
    'zero' => [0, 1],
    'over the ceiling' => [100000, 100],
    'within range passes through' => [3, 3],
]);

it('clamps per_page for a signed-in customer listing orders', function () {
    $customer = User::factory()->verified()->create();
    $token = $customer->createToken('app')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/customer/orders?per_page=-5')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1);
});

it('turns a unique-constraint race into a 409, not a 500', function () {
    $governorate = \App\Models\Governorate::create(['name' => 'بغداد', 'is_active' => true]);
    $district = \App\Models\District::create([
        'governorate_id' => $governorate->id, 'name' => 'الكرخ', 'is_active' => true,
    ]);

    User::factory()->create(['phone' => '9647712345678']);

    // A row already holds the number, and forcing the insert past the service's
    // own pre-check is what a double-tap does under a race. The unique index
    // must answer with a conflict the app can read, not a bare server error.
    $handler = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);
    $request = \Illuminate\Http\Request::create('/api/v1/anything', 'POST');

    $exception = new \Illuminate\Database\UniqueConstraintViolationException(
        'mysql',
        'insert into users ...',
        [],
        new \Exception('SQLSTATE[23000]: 1062 Duplicate entry')
    );

    $response = $handler->render($request, $exception);

    expect($response->getStatusCode())->toBe(409);
    expect(json_decode($response->getContent(), true)['message'])
        ->toBe('هذه العملية نُفّذت مسبقاً، لا داعي لتكرارها');
});
