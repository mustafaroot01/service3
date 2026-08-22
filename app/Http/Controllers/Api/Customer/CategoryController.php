<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\CategoryResource;
use App\Http\Resources\Api\Customer\ServiceResource;
use App\Models\Category;
use App\Models\Service;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::visible()
            ->withCount(['services' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            CategoryResource::collection($categories)->resolve(),
            'Categories retrieved successfully'
        );
    }

    public function services(Category $category): JsonResponse
    {
        if (! $category->is_active) {
            return ApiResponse::notFound('القسم غير متاح');
        }

        $services = Service::visible()
            ->with('images')
            ->where('category_id', $category->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            ServiceResource::collection($services)->resolve(),
            'Services retrieved successfully'
        );
    }
}
