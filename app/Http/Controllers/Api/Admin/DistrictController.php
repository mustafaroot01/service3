<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\StoreDistrictRequest;
use App\Http\Requests\Api\Admin\UpdateDistrictRequest;
use App\Http\Resources\Api\Admin\DistrictResource;
use App\Models\District;
use App\Services\BaseCrudService;
use App\Services\DistrictService;
use Illuminate\Http\JsonResponse;

class DistrictController extends AdminCrudController
{
    public function __construct(private readonly DistrictService $districts)
    {
    }

    protected function service(): BaseCrudService
    {
        return $this->districts;
    }

    protected function resource(): string
    {
        return DistrictResource::class;
    }

    protected function label(): string
    {
        return 'District';
    }

    public function store(StoreDistrictRequest $request): JsonResponse
    {
        return $this->created($request->validated());
    }

    public function update(UpdateDistrictRequest $request, District $district): JsonResponse
    {
        return $this->updated($district, $request->validated());
    }
}
