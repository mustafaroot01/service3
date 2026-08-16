<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\StoreGovernorateRequest;
use App\Http\Requests\Api\Admin\UpdateGovernorateRequest;
use App\Http\Resources\Api\Admin\GovernorateResource;
use App\Models\Governorate;
use App\Services\BaseCrudService;
use App\Services\GovernorateService;
use Illuminate\Http\JsonResponse;

class GovernorateController extends AdminCrudController
{
    public function __construct(private readonly GovernorateService $governorates) {}

    protected function service(): BaseCrudService
    {
        return $this->governorates;
    }

    protected function resource(): string
    {
        return GovernorateResource::class;
    }

    protected function label(): string
    {
        return 'Governorate';
    }

    public function store(StoreGovernorateRequest $request): JsonResponse
    {
        return $this->created($request->validated());
    }

    public function update(UpdateGovernorateRequest $request, Governorate $governorate): JsonResponse
    {
        return $this->updated($governorate, $request->validated());
    }
}
