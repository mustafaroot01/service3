<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\StoreSliderRequest;
use App\Http\Requests\Api\Admin\UpdateSliderRequest;
use App\Http\Resources\Api\Admin\SliderResource;
use App\Models\Slider;
use App\Services\BaseCrudService;
use App\Services\SliderService;
use Illuminate\Http\JsonResponse;

class SliderController extends AdminCrudController
{
    public function __construct(private readonly SliderService $sliders)
    {
    }

    protected function service(): BaseCrudService
    {
        return $this->sliders;
    }

    protected function resource(): string
    {
        return SliderResource::class;
    }

    protected function label(): string
    {
        return 'Slider';
    }

    public function store(StoreSliderRequest $request): JsonResponse
    {
        return $this->created($request->validated());
    }

    public function update(UpdateSliderRequest $request, Slider $slider): JsonResponse
    {
        return $this->updated($slider, $request->validated());
    }
}
