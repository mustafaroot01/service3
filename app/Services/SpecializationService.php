<?php

namespace App\Services;

use App\Models\Specialization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SpecializationService extends BaseCrudService
{
    protected string $modelClass = Specialization::class;

    protected array $searchable = ['name'];

    protected array $sortable = ['id', 'name', 'is_active', 'created_at'];

    protected string $defaultSort = 'name';

    protected array $filterable = ['is_active'];

    protected function baseQuery(): Builder
    {
        return $this->query()->withCount('technicians');
    }

    public function hydrate(Model $model): Model
    {
        return $model->loadCount('technicians');
    }
}
