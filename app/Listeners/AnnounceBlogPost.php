<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\BlogPostPublished;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs synchronously (no ShouldQueue): the host has no queue worker. The whole
 * fan-out is wrapped so a failure while announcing never breaks the publish
 * itself — the post is already saved by the time this runs.
 */
class AnnounceBlogPost
{
    public function __construct(private readonly PushService $push) {}

    public function handle(BlogPostPublished $event): void
    {
        $post = $event->post;

        $title = 'مقال جديد';
        $body = Str::limit($post->title, 120);
        $payload = ['blog_post_id' => $post->id, 'title' => $post->title];

        try {
            // The in-app rows are the reliable record, so they are written first
            // and the push is the mirror. Chunked so a large customer base does
            // not build one huge insert, and read as ids only so the whole table
            // never enters memory.
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

            $this->push->broadcast($title, $body, $payload);
        } catch (Throwable $e) {
            Log::warning('Blog announcement failed', ['post' => $post->id, 'message' => $e->getMessage()]);
        }
    }
}
