<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\StoreBlogPostRequest;
use App\Http\Requests\Api\Admin\UpdateBlogPostRequest;
use App\Http\Resources\Api\Admin\BlogPostResource;
use App\Models\BlogPost;
use App\Services\BaseCrudService;
use App\Services\BlogPostService;
use Illuminate\Http\JsonResponse;

class BlogPostController extends AdminCrudController
{
    public function __construct(private readonly BlogPostService $posts)
    {
    }

    protected function service(): BaseCrudService
    {
        return $this->posts;
    }

    protected function resource(): string
    {
        return BlogPostResource::class;
    }

    protected function label(): string
    {
        return 'Blog post';
    }

    public function store(StoreBlogPostRequest $request): JsonResponse
    {
        return $this->created($request->validated());
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $blog): JsonResponse
    {
        return $this->updated($blog, $request->validated());
    }
}
