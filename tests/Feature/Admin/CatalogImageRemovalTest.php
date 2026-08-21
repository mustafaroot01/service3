<?php

use App\Models\Admin;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

/**
 * Editing a category can now clear its image outright, not just replace it —
 * `remove_image` drops the file and nulls the column, while a plain edit with no
 * new file leaves the existing image untouched.
 */
beforeEach(function () {
    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;

    Storage::fake('public');
    Storage::disk('public')->put('categories/pic.jpg', 'binary');
    $this->category = Category::create(['name' => 'تكييف', 'image' => 'categories/pic.jpg']);

    $this->update = fn (array $data) => $this->actingAs($this->admin, 'admin')
        ->patchJson("/api/v1/admin/categories/{$this->category->id}", $data);
});

it('clears the image and deletes the file when remove_image is set', function () {
    ($this->update)(['name' => 'تكييف', 'remove_image' => true])->assertSuccessful();

    expect($this->category->fresh()->image)->toBeNull();
    Storage::disk('public')->assertMissing('categories/pic.jpg');
});

it('keeps the existing image on a plain edit', function () {
    ($this->update)(['name' => 'تكييف وتبريد'])->assertSuccessful();

    expect($this->category->fresh()->image)->toBe('categories/pic.jpg');
    Storage::disk('public')->assertExists('categories/pic.jpg');
});

it('keeps the image when remove_image is explicitly false', function () {
    ($this->update)(['name' => 'تكييف', 'remove_image' => false])->assertSuccessful();

    expect($this->category->fresh()->image)->toBe('categories/pic.jpg');
});
