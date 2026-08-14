<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ReorderRequest;
use App\Services\BaseCrudService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class AdminCrudController extends Controller
{
    abstract protected function service(): BaseCrudService;

    abstract protected function resource(): string;

    abstract protected function label(): string;

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->service()->list($request),
            $this->resource(),
            "{$this->label()} retrieved successfully"
        );
    }

    public function show(string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->present($this->service()->hydrate($this->find($id))),
            "{$this->label()} retrieved successfully"
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service()->delete($this->find($id));

        return ApiResponse::success(null, "{$this->label()} deleted successfully");
    }

    public function toggle(string $id): JsonResponse
    {
        return ApiResponse::success(
            $this->present($this->service()->toggle($this->find($id))),
            "{$this->label()} status toggled successfully"
        );
    }

    public function reorder(ReorderRequest $request): JsonResponse
    {
        $this->service()->reorder($request->validated()['items']);

        return ApiResponse::success(null, "{$this->label()} reordered successfully");
    }

    protected function find(string $id): Model
    {
        return $this->service()->findOrFail((int) $id);
    }

    protected function created(array $data): JsonResponse
    {
        return ApiResponse::created(
            $this->present($this->service()->create($data)),
            "{$this->label()} created successfully"
        );
    }

    protected function updated(Model $model, array $data): JsonResponse
    {
        return ApiResponse::success(
            $this->present($this->service()->update($model, $data)),
            "{$this->label()} updated successfully"
        );
    }

    protected function present(Model $model)
    {
        $resource = $this->resource();

        return new $resource($model);
    }
}
