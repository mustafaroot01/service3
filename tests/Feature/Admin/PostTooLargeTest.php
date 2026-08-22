<?php

use App\Models\Admin;

/**
 * A request bigger than PHP's post_max_size is rejected before any controller
 * runs. The admin must read a clear Arabic 413 naming the cap, not the raw
 * framework text.
 */
it('explains an oversized upload in Arabic with the current cap', function () {
    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);

    $this->actingAs($admin, 'admin')
        ->call('POST', '/api/v1/admin/services', [], [], [], ['CONTENT_LENGTH' => 9_999_999_999, 'HTTP_ACCEPT' => 'application/json'])
        ->assertStatus(413)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'حجم الملفات المرفوعة كبير جداً — الحد الأقصى للطلب الواحد '.ini_get('post_max_size').'. قلّل حجم الصور أو ارفعها على دفعات.');
});
