<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Api\Admin\OrderResource;
use App\Http\Resources\Api\Admin\UserResource;
use App\Models\User;
use App\Services\BaseCrudService;
use App\Services\OrderService;
use App\Services\UserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends AdminCrudController
{
    public function __construct(
        private readonly UserService $users,
        private readonly OrderService $orders,
    ) {
    }

    protected function service(): BaseCrudService
    {
        return $this->users;
    }

    protected function resource(): string
    {
        return UserResource::class;
    }

    protected function label(): string
    {
        return 'User';
    }

    public function changeStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($this->users->changeStatus($user, $request->status())),
            'تم تحديث حالة المستخدم بنجاح'
        );
    }

    public function dismissDeletionRequest(User $user): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($this->users->dismissDeletionRequest($user)),
            'تم إلغاء طلب حذف الحساب'
        );
    }

    public function orders(Request $request, User $user): JsonResponse
    {
        $orders = $this->orders->paginateRelated($user->orders(), $request);

        return ApiResponse::paginated($orders, OrderResource::class, 'User orders retrieved successfully');
    }
}
