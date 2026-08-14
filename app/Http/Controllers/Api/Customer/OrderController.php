<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\StoreOrderRequest;
use App\Http\Resources\Api\Customer\OrderResource;
use App\Services\CustomerOrderService;
use App\Support\ApiResponse;
use App\Support\VisitWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly CustomerOrderService $orders)
    {
    }

    public function visitWindow(): JsonResponse
    {
        $earliest = VisitWindow::earliestStart();

        return ApiResponse::success([
            'date' => VisitWindow::date()->toDateString(),
            'is_open' => VisitWindow::isOpen(),
            'earliest_from' => $earliest?->format('H:i'),
            'latest_from' => VisitWindow::latestStart()->format('H:i'),
            'may_end_next_day' => true,
            'max_images' => StoreOrderRequest::MAX_IMAGES,
        ], 'Visit window retrieved successfully');
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->orders->paginateFor($request->user(), $request),
            OrderResource::class,
            'Orders retrieved successfully'
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->user(), $request->validated());

        return ApiResponse::created(
            new OrderResource($order),
            "تم استلام طلبك برقم {$order->order_number}، شكراً لك"
        );
    }

    public function show(Request $request, string $order): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($this->orders->findFor($request->user(), (int) $order)),
            'Order retrieved successfully'
        );
    }

    public function cancel(Request $request, string $order): JsonResponse
    {
        return ApiResponse::success(
            new OrderResource($this->orders->cancel($request->user(), (int) $order)),
            'تم إلغاء الطلب'
        );
    }
}
