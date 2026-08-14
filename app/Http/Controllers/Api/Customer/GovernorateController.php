<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\DistrictResource;
use App\Http\Resources\Api\Customer\GovernorateResource;
use App\Models\District;
use App\Models\Governorate;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class GovernorateController extends Controller
{
    public function index(): JsonResponse
    {
        $governorates = Governorate::visible()
            ->withCount(['districts' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            GovernorateResource::collection($governorates)->resolve(),
            'Governorates retrieved successfully'
        );
    }

    public function districts(Governorate $governorate): JsonResponse
    {
        if (! $governorate->is_active) {
            return ApiResponse::notFound('المحافظة غير متاحة');
        }

        $districts = District::visible()
            ->where('governorate_id', $governorate->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            DistrictResource::collection($districts)->resolve(),
            'Districts retrieved successfully'
        );
    }
}
