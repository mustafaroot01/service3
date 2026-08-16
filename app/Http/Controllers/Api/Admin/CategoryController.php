<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\Admin\UpdateCategoryRequest;
use App\Http\Resources\Api\Admin\CategoryResource;
use App\Models\Category;
use App\Services\BaseCrudService;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends AdminCrudController
{
    public function __construct(private readonly CategoryService $categories) {}

    protected function service(): BaseCrudService
    {
        return $this->categories;
    }

    protected function resource(): string
    {
        return CategoryResource::class;
    }

    protected function label(): string
    {
        return 'Category';
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return $this->created($request->validated());
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        return $this->updated($category, $request->validated());
    }
}
