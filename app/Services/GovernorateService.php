<?php

namespace App\Services;

use App\Models\Governorate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GovernorateService extends BaseCrudService
{
    protected string $modelClass = Governorate::class;

    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'name', 'is_active', 'sort_order', 'created_at'];

    protected string $defaultSort = 'sort_order';

    protected array $filterable = ['is_active'];

    protected function baseQuery(): Builder
    {
        return $this->query()->withCount(['districts', 'users', 'technicians']);
    }

    public function hydrate(Model $model): Model
    {
        return $model->loadCount(['districts', 'users', 'technicians']);
    }
}
