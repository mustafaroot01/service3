<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\BlogPostPublished;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class AnnounceBlogPost implements ShouldQueue
{
    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    public array $backoff = [10, 60];

    use InteractsWithQueue;

    public function __construct(private readonly PushService $push) {}

    public function handle(BlogPostPublished $event): void
    {
        $post = $event->post;

        $title = 'مقال جديد';
        $body = Str::limit($post->title, 120);
        $payload = ['blog_post_id' => $post->id, 'title' => $post->title];

        $this->push->broadcast($title, $body, $payload);

        // Written in chunks so a large customer base does not build one huge
        // insert, and read as ids only so the whole table never enters memory.
        User::query()->select('id')->chunkById(500, function ($users) use ($title, $body, $payload) {
            $now = now();

            Notification::insert($users->map(fn (User $user) => [
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'type' => NotificationType::BLOG_POST->value,
                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }
}
