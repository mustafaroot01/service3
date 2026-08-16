<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

abstract class BaseCrudService extends BaseService
{
    protected array $searchable = [];

    protected array $sortable = ['id'];

    protected string $defaultSort = 'id';

    protected array $filterable = [];

    protected array $imageFields = [];

    protected string $imageDirectory = 'uploads';

    protected function baseQuery(): Builder
    {
        return $this->query();
    }

    protected function applyFilters(Builder $query, Request $request): void {}

    /** A caller-supplied page size is untrusted input; an unbounded one loads the whole table. */
    protected function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->input('per_page', 15)));
    }

    public function list(Request $request): LengthAwarePaginator
    {
        $query = $this->baseQuery();

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function (Builder $q) use ($search) {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ($this->filterable as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }

        $this->applyFilters($query, $request);

        $sortBy = (string) $request->input('sortBy', $this->defaultSort);
        if (! in_array($sortBy, $this->sortable, true)) {
            $sortBy = $this->defaultSort;
        }

        $orderBy = strtolower((string) $request->input('orderBy', 'asc')) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($sortBy, $orderBy)
            ->paginate($this->perPage($request))
            ->appends($request->query());
    }

    public function create(array $data): Model
    {
        $data = $this->storeImages($data);

        $model = $this->model->newInstance();
        $model->fill($data)->save();

        return $this->hydrate($model->refresh());
    }

    public function update(Model $model, array $data): Model
    {
        $data = $this->storeImages($data, $model);

        $model->fill($data)->save();

        return $this->hydrate($model->refresh());
    }

    public function delete(Model $model): void
    {
        foreach ($this->imageFields as $field) {
            $this->deleteImage($model->{$field});
        }

        $model->delete();
    }

    public function toggle(Model $model, string $column = 'is_active'): Model
    {
        $model->{$column} = ! $model->{$column};
        $model->save();

        return $this->hydrate($model->refresh());
    }

    public function reorder(array $items, string $column = 'sort_order'): void
    {
        $ids = array_column($items, 'id');
        $known = $this->query()->whereKey($ids)->pluck('id')->all();

        if ($missing = array_diff($ids, $known)) {
            throw ValidationException::withMessages([
                'items' => 'بعض العناصر غير موجودة: '.implode('، ', $missing),
            ]);
        }

        DB::transaction(function () use ($items, $column) {
            foreach ($items as $item) {
                $this->model->newQuery()
                    ->whereKey($item['id'])
                    ->update([$column => $item['sort_order']]);
            }
        });
    }

    public function hydrate(Model $model): Model
    {
        return $model;
    }

    protected function storeImages(array $data, ?Model $existing = null): array
    {
        foreach ($this->imageFields as $field) {
            if (! isset($data[$field])) {
                continue;
            }

            if (! $data[$field] instanceof UploadedFile) {
                unset($data[$field]);

                continue;
            }

            if ($existing) {
                $this->deleteImage($existing->{$field});
            }

            $data[$field] = $data[$field]->store($this->imageDirectory, 'public');
        }

        return $data;
    }

    protected function deleteImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
