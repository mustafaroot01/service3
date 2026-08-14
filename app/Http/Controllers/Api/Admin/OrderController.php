<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\AssignTechnicianRequest;
use App\Http\Requests\Api\Admin\CancelOrderRequest;
use App\Http\Requests\Api\Admin\InspectOrderRequest;
use App\Http\Resources\Api\Admin\OrderResource;
use App\Http\Resources\Api\Admin\TechnicianResource;
use App\Models\Order;
use App\Models\Technician;
use App\Services\BaseCrudService;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrderController extends AdminCrudController
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    protected function service(): BaseCrudService
    {
        return $this->orders;
    }

    protected function resource(): string
    {
        return OrderResource::class;
    }

    protected function label(): string
    {
        return 'Order';
    }

    public function confirm(Order $order): JsonResponse
    {
        return $this->respond($this->orders->confirm($order), 'تم تأكيد الطلب');
    }

    public function assignTechnician(AssignTechnicianRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->assignTechnician($order, (int) $request->validated('technician_id'));

        return $this->respond($order, 'تم تعيين الفني بنجاح');
    }

    public function reassignTechnician(AssignTechnicianRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->reassignTechnician($order, (int) $request->validated('technician_id'));

        return $this->respond($order, 'تم استبدال الفني بنجاح');
    }

    public function inspect(InspectOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->orders->inspect($order, $request->validated('inspection_note'));

        return $this->respond($order, 'تم تسجيل الكشف');
    }

    public function complete(Order $order): JsonResponse
    {
        return $this->respond($this->orders->complete($order), 'تم إنجاز الخدمة');
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        return $this->respond($this->orders->cancel($order, $request->validated('note')), 'تم إلغاء الطلب');
    }

    public function availableTechnicians(Order $order): JsonResponse
    {
        $technicians = Technician::assignableTo($order->governorate_id)
            ->with(['specializations', 'district'])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            TechnicianResource::collection($technicians)->resolve(),
            'Available technicians retrieved successfully'
        );
    }

    private function respond(Order $order, string $message): JsonResponse
    {
        return ApiResponse::success(new OrderResource($order), $message);
    }
}
