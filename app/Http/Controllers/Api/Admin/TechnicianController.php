<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\StoreTechnicianRequest;
use App\Http\Requests\Api\Admin\UpdateTechnicianRequest;
use App\Http\Requests\Api\Admin\UpdateTechnicianStatusRequest;
use App\Http\Resources\Api\Admin\OrderResource;
use App\Http\Resources\Api\Admin\TechnicianResource;
use App\Models\Technician;
use App\Models\TechnicianMedia;
use App\Services\BaseCrudService;
use App\Services\OrderService;
use App\Services\TechnicianService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicianController extends AdminCrudController
{
    public function __construct(
        private readonly TechnicianService $technicians,
        private readonly OrderService $orders,
    ) {}

    protected function service(): BaseCrudService
    {
        return $this->technicians;
    }

    protected function resource(): string
    {
        return TechnicianResource::class;
    }

    protected function label(): string
    {
        return 'Technician';
    }

    public function store(StoreTechnicianRequest $request): JsonResponse
    {
        return $this->created($request->validated());
    }

    public function update(UpdateTechnicianRequest $request, Technician $technician): JsonResponse
    {
        return $this->updated($technician, $request->validated());
    }

    public function changeStatus(UpdateTechnicianStatusRequest $request, Technician $technician): JsonResponse
    {
        return ApiResponse::success(
            new TechnicianResource($this->technicians->changeStatus($technician, $request->status())),
            'تم تحديث حالة الفني بنجاح'
        );
    }

    public function destroyMedia(Technician $technician, TechnicianMedia $media): JsonResponse
    {
        $this->technicians->deleteMedia($technician, $media);

        return ApiResponse::success(null, 'تم حذف الملف بنجاح');
    }

    public function orders(Request $request, Technician $technician): JsonResponse
    {
        $orders = $this->orders->paginateRelated($technician->orders(), $request);

        return ApiResponse::paginated($orders, OrderResource::class, 'Technician orders retrieved successfully');
    }
}
