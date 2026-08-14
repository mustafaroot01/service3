<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\BlogPostResource;
use App\Models\BlogPost;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = BlogPost::visible()
            ->orderByDesc('published_at')
            ->paginate((int) $request->input('per_page', 10))
            ->appends($request->query());

        return ApiResponse::paginated($posts, BlogPostResource::class, 'Blog posts retrieved successfully');
    }

    public function show(BlogPost $blog): JsonResponse
    {
        if (! BlogPost::visible()->whereKey($blog->id)->exists()) {
            return ApiResponse::notFound('المقال غير متاح');
        }

        return ApiResponse::success(new BlogPostResource($blog), 'Blog post retrieved successfully');
    }
}
