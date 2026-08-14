<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\SpecializationResource;
use App\Models\Specialization;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SpecializationController extends Controller
{
    public function index(): JsonResponse
    {
        $specializations = Specialization::where('is_active', true)->orderBy('name')->get();

        return ApiResponse::success(
            SpecializationResource::collection($specializations)->resolve(),
            'Specializations retrieved successfully'
        );
    }
}
