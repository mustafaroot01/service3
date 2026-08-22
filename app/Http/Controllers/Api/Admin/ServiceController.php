<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\ReplaceServiceImageRequest;
use App\Http\Requests\Api\Admin\StoreServiceImagesRequest;
use App\Http\Requests\Api\Admin\StoreServiceRequest;
use App\Http\Requests\Api\Admin\UpdateServiceRequest;
use App\Http\Resources\Api\Admin\ServiceResource;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Services\BaseCrudService;
use App\Services\ServiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceController extends AdminCrudController
{
    public function __construct(private readonly ServiceService $services) {}

    protected function service(): BaseCrudService
    {
        return $this->services;
    }

    protected function resource(): string
    {
        return ServiceResource::class;
    }

    protected function label(): string
    {
        return 'Service';
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        return $this->created($request->validated());
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        return $this->updated($service, $request->validated());
    }

    public function storeImages(StoreServiceImagesRequest $request, Service $service): JsonResponse
    {
        return ApiResponse::success(
            $this->present($this->services->addImages($service, $request->file('images', []))),
            'تمت إضافة الصور بنجاح'
        );
    }

    public function replaceImage(ReplaceServiceImageRequest $request, Service $service, ServiceImage $image): JsonResponse
    {
        return ApiResponse::success(
            $this->present($this->services->replaceImage($service, $image, $request->file('image'))),
            'تم استبدال الصورة بنجاح'
        );
    }

    public function destroyImage(Service $service, ServiceImage $image): JsonResponse
    {
        return ApiResponse::success(
            $this->present($this->services->removeImage($service, $image)),
            'تم حذف الصورة بنجاح'
        );
    }
}
