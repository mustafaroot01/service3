<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\ChangePasswordRequest;
use App\Http\Requests\Api\Customer\UpdateProfileRequest;
use App\Http\Resources\Api\Customer\UserResource;
use App\Services\CustomerAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function __construct(private readonly CustomerAuthService $auth)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load(['governorate', 'district'])->loadCount('orders')),
            'Profile retrieved successfully'
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return ApiResponse::success(
            new UserResource($user->refresh()->load(['governorate', 'district'])->loadCount('orders')),
            'تم تحديث بياناتك بنجاح'
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $current = $request->user()->currentAccessToken();

        DB::transaction(function () use ($user, $request, $current) {
            $user->password = $request->validated('password');
            $user->save();
            $user->tokens()->whereKeyNot($current->getKey())->delete();
        });

        return ApiResponse::success(null, 'تم تغيير كلمة السر، وسُجّل الخروج من بقية الأجهزة');
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $this->auth->requestDeletion($request->user());

        return ApiResponse::success(null, 'تم إرسال طلب الحذف إلى الإدارة');
    }
}
