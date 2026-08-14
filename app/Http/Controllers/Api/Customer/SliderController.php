<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\SliderResource;
use App\Models\Slider;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SliderController extends Controller
{
    public function index(): JsonResponse
    {
        $sliders = Slider::visible()->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success(
            SliderResource::collection($sliders)->resolve(),
            'Sliders retrieved successfully'
        );
    }
}
