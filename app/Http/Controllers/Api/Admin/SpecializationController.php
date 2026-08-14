<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\StoreSpecializationRequest;
use App\Http\Requests\Api\Admin\UpdateSpecializationRequest;
use App\Http\Resources\Api\Admin\SpecializationResource;
use App\Models\Specialization;
use App\Services\BaseCrudService;
use App\Services\SpecializationService;
use Illuminate\Http\JsonResponse;

class SpecializationController extends AdminCrudController
{
    public function __construct(private readonly SpecializationService $specializations)
    {
    }

    protected function service(): BaseCrudService
    {
        return $this->specializations;
    }

    protected function resource(): string
    {
        return SpecializationResource::class;
    }

    protected function label(): string
    {
        return 'Specialization';
    }

    public function store(StoreSpecializationRequest $request): JsonResponse
    {
        return $this->created($request->validated());
    }

    public function update(UpdateSpecializationRequest $request, Specialization $specialization): JsonResponse
    {
        return $this->updated($specialization, $request->validated());
    }
}
