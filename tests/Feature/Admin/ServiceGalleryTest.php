<?php

use App\Models\Admin;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A service carries a gallery of up to four images. The first one is the cover
 * that every `image` consumer still reads; each image can be added, replaced in
 * place, or removed, and no file ever outlives its row.
 */
beforeEach(function () {
    Storage::fake('public');

    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;

    $this->category = Category::create(['name' => 'تكييف']);
    $this->service = Service::create(['category_id' => $this->category->id, 'name' => 'صيانة مكيّفات']);

    $this->img = fn (string $name = 'a.png') => UploadedFile::fake()->image($name, 40, 40);
    $this->as = fn () => $this->actingAs($this->admin, 'admin');
    $this->seed = function (int $n) {
        foreach (range(1, $n) as $i) {
            Storage::disk('public')->put("services/s{$i}.png", 'x');
            $this->service->images()->create(['path' => "services/s{$i}.png", 'sort' => $i - 1]);
        }
    };
});

it('creates a service with up to four images, the first being the cover', function () {
    $res = ($this->as)()->post('/api/v1/admin/services', [
        'category_id' => $this->category->id,
        'name' => 'تركيب مكيّفات',
        'images' => [($this->img)('1.png'), ($this->img)('2.png'), ($this->img)('3.png'), ($this->img)('4.png')],
    ])->assertCreated();

    $images = $res->json('data.images');

    expect($images)->toHaveCount(4)
        ->and($res->json('data.image'))->toBe($images[0]['url'])
        ->and(array_column($images, 'sort'))->toBe([0, 1, 2, 3]);

    foreach (Service::where('name', 'تركيب مكيّفات')->firstOrFail()->images as $image) {
        Storage::disk('public')->assertExists($image->path);
    }
});

it('refuses a fifth image on create', function () {
    ($this->as)()->post('/api/v1/admin/services', [
        'category_id' => $this->category->id,
        'name' => 'تركيب مكيّفات',
        'images' => array_map(fn ($i) => ($this->img)("{$i}.png"), range(1, 5)),
    ])->assertStatus(422)->assertJsonValidationErrors('images');
});

it('adds images only within the remaining room', function () {
    ($this->seed)(3);

    ($this->as)()->post("/api/v1/admin/services/{$this->service->id}/images", [
        'images' => [($this->img)()],
    ])->assertOk()->assertJsonCount(4, 'data.images');

    ($this->as)()->post("/api/v1/admin/services/{$this->service->id}/images", [
        'images' => [($this->img)()],
    ])->assertStatus(422)->assertJsonPath('errors.images.0', 'بلغت الحد الأقصى (4) لصور الخدمة، احذف واحدة قبل الإضافة');

    expect($this->service->images()->count())->toBe(4);
});

it('replaces an image in place, keeping its position and dropping the old file', function () {
    ($this->seed)(3);
    $middle = $this->service->images()->where('sort', 1)->firstOrFail();

    $res = ($this->as)()->post("/api/v1/admin/services/{$this->service->id}/images/{$middle->id}", [
        'image' => ($this->img)('new.png'),
    ])->assertOk();

    $fresh = $middle->fresh();

    expect($fresh->sort)->toBe(1)
        ->and($fresh->path)->not->toBe('services/s2.png')
        ->and($res->json('data.images.1.id'))->toBe($middle->id);

    Storage::disk('public')->assertMissing('services/s2.png');
    Storage::disk('public')->assertExists($fresh->path);
});

it('removes an image together with its file', function () {
    ($this->seed)(2);
    $first = $this->service->images()->where('sort', 0)->firstOrFail();

    ($this->as)()->deleteJson("/api/v1/admin/services/{$this->service->id}/images/{$first->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.images');

    expect(ServiceImage::whereKey($first->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('services/s1.png');
});

it('promotes the next image to cover when the first is removed', function () {
    ($this->seed)(2);
    $first = $this->service->images()->where('sort', 0)->firstOrFail();

    $res = ($this->as)()->deleteJson("/api/v1/admin/services/{$this->service->id}/images/{$first->id}")->assertOk();

    expect($res->json('data.image'))->toBe($res->json('data.images.0.url'))
        ->and($res->json('data.images.0.url'))->toContain('services/s2.png');
});

it('refuses to touch an image that belongs to another service', function () {
    $other = Service::create(['category_id' => $this->category->id, 'name' => 'أخرى']);
    $foreign = $other->images()->create(['path' => 'services/o.png', 'sort' => 0]);

    ($this->as)()->deleteJson("/api/v1/admin/services/{$this->service->id}/images/{$foreign->id}")->assertNotFound();

    expect(ServiceImage::whereKey($foreign->id)->exists())->toBeTrue();
});

it('removes every gallery file when the service itself is deleted', function () {
    ($this->seed)(3);

    ($this->as)()->deleteJson("/api/v1/admin/services/{$this->service->id}")->assertSuccessful();

    expect(ServiceImage::where('service_id', $this->service->id)->count())->toBe(0);
    foreach (range(1, 3) as $i) {
        Storage::disk('public')->assertMissing("services/s{$i}.png");
    }
});

it('exposes the gallery and the cover to the customer app', function () {
    ($this->seed)(2);

    $this->getJson("/api/v1/customer/services/{$this->service->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.images')
        ->assertJsonPath('data.images.0.id', $this->service->images()->where('sort', 0)->value('id'));

    $res = $this->getJson('/api/v1/customer/services')->assertOk();

    expect($res->json('data.0.image'))->toBe($res->json('data.0.images.0.url'));
});
