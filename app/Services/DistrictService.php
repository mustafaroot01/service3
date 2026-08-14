<?php

namespace App\Services;

use App\Models\District;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DistrictService extends BaseCrudService
{
    protected string $modelClass = District::class;

    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'name', 'is_active', 'sort_order', 'created_at'];

    protected string $defaultSort = 'sort_order';

    protected array $filterable = ['is_active', 'governorate_id'];

    protected function baseQuery(): Builder
    {
        return $this->query()->with('governorate')->withCount(['users', 'technicians']);
    }

    public function hydrate(Model $model): Model
    {
        return $model->load('governorate')->loadCount(['users', 'technicians']);
    }
}
