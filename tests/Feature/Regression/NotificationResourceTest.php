<?php

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;

/**
 * The list endpoint is the only place most customers ever read a notification —
 * the push is gone in a second. So every type's payload has to survive the
 * resource: a blog announcement with no blog_post_id is a card that opens nothing.
 */
beforeEach(function () {
    $this->customer = User::factory()->verified()->create();
    $this->token = $this->customer->createToken('app')->plainTextToken;
});

function pushNotification(User $user, NotificationType $type, array $data): Notification
{
    return Notification::create([
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'title' => 'عنوان',
        'body' => 'نص',
        'type' => $type,
        'data' => $data,
    ]);
}

it('carries blog_post_id and the full payload for a blog notification', function () {
    pushNotification($this->customer, NotificationType::BLOG_POST, [
        'blog_post_id' => 7,
        'title' => 'خمس علامات على تسريب',
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/customer/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'blog_post')
        ->assertJsonPath('data.0.blog_post_id', 7)
        ->assertJsonPath('data.0.order_id', null)
        ->assertJsonPath('data.0.data.blog_post_id', 7)
        ->assertJsonPath('data.0.data.title', 'خمس علامات على تسريب');
});

it('keeps the flattened order fields working for an order notification', function () {
    pushNotification($this->customer, NotificationType::ORDER_STATUS, [
        'order_id' => 41,
        'order_number' => 'HS-260816-0007',
        'from_status' => 'confirmed',
        'to_status' => 'assigned',
    ]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/customer/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.order_id', 41)
        ->assertJsonPath('data.0.order_number', 'HS-260816-0007')
        ->assertJsonPath('data.0.status', 'assigned')
        ->assertJsonPath('data.0.blog_post_id', null)
        ->assertJsonPath('data.0.data.from_status', 'confirmed')
        ->assertJsonPath('data.0.data.to_status', 'assigned');
});

it('never leaks one customer notifications to another', function () {
    $other = User::factory()->verified()->create();
    pushNotification($other, NotificationType::BLOG_POST, ['blog_post_id' => 9]);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/customer/notifications')
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});
