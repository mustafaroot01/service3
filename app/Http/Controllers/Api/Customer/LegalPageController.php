<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\LegalPageKey;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\LegalPageResource;
use App\Models\LegalPage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LegalPageController extends Controller
{
    public function show(string $key): JsonResponse
    {
        if (! LegalPageKey::tryFrom($key)) {
            return ApiResponse::notFound('الصفحة غير موجودة');
        }

        $page = LegalPage::where('key', $key)->first();

        if (! $page) {
            return ApiResponse::notFound('الصفحة غير موجودة');
        }

        return ApiResponse::success(new LegalPageResource($page), 'Legal page retrieved successfully');
    }
}
