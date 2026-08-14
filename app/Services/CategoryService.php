<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CategoryService extends BaseCrudService
{
    protected string $modelClass = Category::class;

    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'name', 'is_active', 'sort_order', 'created_at'];

    protected string $defaultSort = 'sort_order';

    protected array $filterable = ['is_active'];

    protected array $imageFields = ['image'];

    protected string $imageDirectory = 'categories';

    protected function baseQuery(): Builder
    {
        return $this->query()->withCount('services');
    }

    public function hydrate(Model $model): Model
    {
        return $model->loadCount('services');
    }
}
