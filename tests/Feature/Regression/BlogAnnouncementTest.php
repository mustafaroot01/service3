<?php

use App\Enums\NotificationType;
use App\Events\BlogPostPublished;
use App\Listeners\AnnounceBlogPost;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Support\Facades\Event;

/**
 * A push cannot be recalled, so the announcement must fire exactly once — on
 * the first moment the post is readable in the app, never on later edits.
 */
beforeEach(function () {
    $admin = Admin::factory()->create();
    $admin->syncRoles(['super-admin']);
    $this->admin = $admin;

    $this->payload = fn (array $overrides = []) => array_merge([
        'title' => 'نصائح لصيانة المكيّف',
        'content' => 'محتوى المقال',
        'published_at' => now()->toDateTimeString(),
        'is_active' => true,
    ], $overrides);
});

it('announces a post the moment it becomes readable', function () {
    Event::fake([BlogPostPublished::class]);

    $this->actingAs($this->admin, 'admin')
        ->postJson('/api/v1/admin/blog', ($this->payload)())
        ->assertCreated()
        ->assertJsonPath('data.is_announced', true);

    Event::assertDispatched(BlogPostPublished::class);
    expect(BlogPost::sole()->notified_at)->not->toBeNull();
});

it('stays quiet for a draft', function () {
    Event::fake([BlogPostPublished::class]);

    $this->actingAs($this->admin, 'admin')
        ->postJson('/api/v1/admin/blog', ($this->payload)(['is_active' => false]))
        ->assertCreated()
        ->assertJsonPath('data.is_announced', false);

    Event::assertNotDispatched(BlogPostPublished::class);
});

it('stays quiet for a post dated in the future', function () {
    Event::fake([BlogPostPublished::class]);

    $this->actingAs($this->admin, 'admin')
        ->postJson('/api/v1/admin/blog', ($this->payload)(['published_at' => now()->addDay()->toDateTimeString()]))
        ->assertCreated()
        ->assertJsonPath('data.is_announced', false);

    Event::assertNotDispatched(BlogPostPublished::class);
});

it('never announces the same post twice', function () {
    $id = $this->actingAs($this->admin, 'admin')
        ->postJson('/api/v1/admin/blog', ($this->payload)())->json('data.id');

    $announcedAt = BlogPost::find($id)->notified_at;

    Event::fake([BlogPostPublished::class]);

    $this->actingAs($this->admin, 'admin')
        ->putJson("/api/v1/admin/blog/{$id}", ($this->payload)(['title' => 'عنوان منقّح']))->assertOk();

    $this->actingAs($this->admin, 'admin')->postJson("/api/v1/admin/blog/{$id}/toggle")->assertOk();
    $this->actingAs($this->admin, 'admin')->postJson("/api/v1/admin/blog/{$id}/toggle")->assertOk();

    Event::assertNotDispatched(BlogPostPublished::class);
    expect(BlogPost::find($id)->notified_at->toIso8601String())->toBe($announcedAt->toIso8601String());
});

it('announces a draft on the day it is switched on', function () {
    $id = $this->actingAs($this->admin, 'admin')
        ->postJson('/api/v1/admin/blog', ($this->payload)(['is_active' => false]))->json('data.id');

    Event::fake([BlogPostPublished::class]);

    $this->actingAs($this->admin, 'admin')
        ->postJson("/api/v1/admin/blog/{$id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_announced', true);

    Event::assertDispatched(BlogPostPublished::class);
});

it('reaches every customer once, in app and by push', function () {
    User::factory()->verified()->count(3)->create();
    $post = BlogPost::create(($this->payload)());

    $push = Mockery::mock(PushService::class);
    $push->shouldReceive('broadcast')->once()->andReturn(true);

    (new AnnounceBlogPost($push))->handle(new BlogPostPublished($post));

    $rows = Notification::where('type', NotificationType::BLOG_POST)->get();

    expect($rows)->toHaveCount(User::count())
        ->and($rows->pluck('notifiable_id')->unique())->toHaveCount(User::count())
        ->and($rows->pluck('notifiable_type')->unique()->all())->toBe(['user'])
        ->and($rows->first()->data['blog_post_id'])->toBe($post->id);
});

it('drops the job rather than failing it when the post is gone', function () {
    expect((new AnnounceBlogPost(app(PushService::class)))->deleteWhenMissingModels)->toBeTrue();
});
