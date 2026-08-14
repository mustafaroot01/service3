<?php

namespace App\Services;

use App\Events\BlogPostPublished;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Model;

class BlogPostService extends BaseCrudService
{
    protected string $modelClass = BlogPost::class;

    protected array $searchable = ['title', 'content'];

    protected array $sortable = ['id', 'title', 'is_active', 'published_at', 'created_at'];

    protected string $defaultSort = 'published_at';

    protected array $filterable = ['is_active'];

    protected array $imageFields = ['image'];

    protected string $imageDirectory = 'blog';

    public function create(array $data): Model
    {
        return $this->announceIfPublished(parent::create($data));
    }

    public function update(Model $model, array $data): Model
    {
        return $this->announceIfPublished(parent::update($model, $data));
    }

    public function toggle(Model $model, string $column = 'is_active'): Model
    {
        return $this->announceIfPublished(parent::toggle($model, $column));
    }

    /**
     * The announcement goes out the first time a post is actually readable in
     * the app, never on the edits that follow — a push cannot be taken back.
     */
    private function announceIfPublished(Model $post): Model
    {
        if ($post->notified_at !== null || ! BlogPost::visible()->whereKey($post->getKey())->exists()) {
            return $post;
        }

        $post->forceFill(['notified_at' => now()])->save();

        BlogPostPublished::dispatch($post);

        return $post;
    }
}
