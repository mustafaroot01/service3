<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServiceService extends BaseCrudService
{
    protected string $modelClass = Service::class;

    protected array $searchable = ['name', 'description'];

    protected array $sortable = ['id', 'name', 'is_active', 'sort_order', 'orders_count', 'created_at'];

    protected string $defaultSort = 'sort_order';

    protected array $filterable = ['is_active', 'category_id'];

    protected array $imageFields = ['image'];

    protected string $imageDirectory = 'services';

    protected function baseQuery(): Builder
    {
        return $this->query()->with('category')->withCount('orders');
    }

    public function hydrate(Model $model): Model
    {
        return $model->load('category')->loadCount('orders');
    }
}
