<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\LoginRequest;
use App\Http\Resources\Api\Admin\AdminResource;
use App\Models\Admin;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const TOKEN_NAME = 'admin-token';

    public function login(LoginRequest $request): JsonResponse
    {
        $admin = Admin::where('email', $request->validated('email'))->first();

        if (! $admin || ! Hash::check($request->validated('password'), $admin->password)) {
            return ApiResponse::unauthorized('البريد الإلكتروني أو كلمة المرور غير صحيحة');
        }

        if (! $admin->isActive()) {
            return ApiResponse::forbidden('حسابك غير مفعّل، راجع الإدارة');
        }

        return ApiResponse::success(
            $this->tokenPayload($admin),
            'تم تسجيل الدخول بنجاح'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new AdminResource($request->user()->load('roles.permissions')),
            'Admin retrieved successfully'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'تم تسجيل الخروج بنجاح');
    }

    public function refresh(Request $request): JsonResponse
    {
        $admin = $request->user();
        $admin->currentAccessToken()?->delete();

        return ApiResponse::success($this->tokenPayload($admin), 'تم تجديد التوكن بنجاح');
    }

    private function tokenPayload(Admin $admin): array
    {
        $admin->load('roles.permissions');

        return [
            'admin' => (new AdminResource($admin))->resolve(),
            'token' => $admin->createToken(self::TOKEN_NAME, $admin->getAllPermissions()->pluck('name')->all())->plainTextToken,
            'token_type' => 'Bearer',
        ];
    }
}
