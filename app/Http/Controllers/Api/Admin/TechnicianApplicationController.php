<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\UpdateApplicationStatusRequest;
use App\Http\Resources\Api\Admin\TechnicianApplicationResource;
use App\Http\Resources\Api\Admin\TechnicianResource;
use App\Models\TechnicianApplication;
use App\Services\BaseCrudService;
use App\Services\TechnicianApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TechnicianApplicationController extends AdminCrudController
{
    public function __construct(private readonly TechnicianApplicationService $applications)
    {
    }

    protected function service(): BaseCrudService
    {
        return $this->applications;
    }

    protected function resource(): string
    {
        return TechnicianApplicationResource::class;
    }

    protected function label(): string
    {
        return 'Technician application';
    }

    public function changeStatus(UpdateApplicationStatusRequest $request, TechnicianApplication $application): JsonResponse
    {
        $application = $this->applications->changeStatus(
            $application,
            $request->status(),
            $request->validated('note')
        );

        return ApiResponse::success(
            new TechnicianApplicationResource($application),
            'تم تحديث حالة الاستمارة'
        );
    }

    public function accept(TechnicianApplication $application): JsonResponse
    {
        $technician = $this->applications->accept($application);

        return ApiResponse::success(
            ['technician' => new TechnicianResource($technician)],
            "تم قبول {$technician->name} ونقله إلى الفنيين — فعّله من ملفه ليبدأ استلام الطلبات"
        );
    }

    public function pendingCount(): JsonResponse
    {
        return ApiResponse::success(
            ['count' => $this->applications->pendingCount()],
            'Pending applications counted successfully'
        );
    }
}
