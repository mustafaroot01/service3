<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
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
    public function __construct(private readonly TechnicianApplicationService $applications) {}

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

    public function destroy(string $id): JsonResponse
    {
        $application = TechnicianApplication::findOrFail((int) $id);

        // Only a rejected form is finished business. A pending or under-review
        // one is a real applicant still waiting on an answer, and deleting it
        // silently drops him along with his uploaded documents.
        if ($application->status !== ApplicationStatus::REJECTED) {
            return ApiResponse::error(
                'لا يمكن حذف إلا الاستمارات المرفوضة',
                ['status' => ['لا يمكن حذف إلا الاستمارات المرفوضة']],
                422
            );
        }

        $this->applications->delete($application);

        return ApiResponse::success(null, 'تم حذف الاستمارة ومرفقاتها');
    }

    public function pendingCount(): JsonResponse
    {
        return ApiResponse::success(
            ['count' => $this->applications->pendingCount()],
            'Pending applications counted successfully'
        );
    }
}
