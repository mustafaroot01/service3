<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class BaseService
{
    protected string $modelClass;

    protected Model $model;

    public function __construct()
    {
        $this->model = app($this->modelClass);
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }
}
