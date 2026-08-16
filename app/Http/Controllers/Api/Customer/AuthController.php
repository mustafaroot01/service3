<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\LoginRequest;
use App\Http\Requests\Api\Customer\PhoneRequest;
use App\Http\Requests\Api\Customer\RegisterRequest;
use App\Http\Requests\Api\Customer\ResetPasswordRequest;
use App\Http\Requests\Api\Customer\VerifyOtpRequest;
use App\Http\Resources\Api\Customer\UserResource;
use App\Services\CustomerAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly CustomerAuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return ApiResponse::created(
            $this->auth->register($request->validated()),
            'أرسلنا رمز التحقق إلى واتساب'
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->auth->verifyRegistration(
                $request->validated('phone'),
                $request->validated('code')
            ),
            'تم توثيق رقمك، سجّل الدخول للمتابعة'
        );
    }

    public function resendOtp(PhoneRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->auth->resend($request->validated('phone')),
            'تم إرسال رمز جديد'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('phone'),
            $request->validated('password')
        );

        return $this->authPayload($result, 'تم تسجيل الدخول بنجاح');
    }

    public function forgotPassword(PhoneRequest $request): JsonResponse
    {
        return ApiResponse::success(
            $this->auth->forgotPassword($request->validated('phone')),
            'أرسلنا رمز استعادة كلمة السر إلى واتساب'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword(
            $request->validated('phone'),
            $request->validated('code'),
            $request->validated('password')
        );

        return ApiResponse::success(null, 'تم تغيير كلمة السر، سجّل الدخول بكلمتك الجديدة');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load(['governorate', 'district'])->loadCount('orders')),
            'User retrieved successfully'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'تم تسجيل الخروج بنجاح');
    }

    private function authPayload(array $result, string $message, int $code = 200): JsonResponse
    {
        return ApiResponse::success([
            'user' => (new UserResource($result['user']))->resolve(),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], $message, $code);
    }
}
