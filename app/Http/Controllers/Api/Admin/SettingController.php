<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateSettingsRequest;
use App\Services\SettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->settings->presentGrouped(), 'Settings retrieved successfully');
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->updateMany($request->pairs());

        return ApiResponse::success($this->settings->presentGrouped(), 'تم حفظ الإعدادات بنجاح');
    }
}
