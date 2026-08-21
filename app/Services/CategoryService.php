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

    /**
     * Deleting a category cascades to its services, which would trip the orders
     * RESTRICT and 500. A category any of whose services has orders is retired by
     * deactivating it, never deleted.
     */
    public function delete(Model $model): void
    {
        if ($model->services()->whereHas('orders')->exists()) {
            abort(422, 'لا يمكن حذف قسم مرتبط بطلبات على خدماته. عطّله بدل الحذف.');
        }

        parent::delete($model);
    }
}
