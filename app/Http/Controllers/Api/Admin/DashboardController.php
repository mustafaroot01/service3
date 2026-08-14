<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): JsonResponse
    {
        return ApiResponse::success($dashboard->summary(), 'Dashboard summary retrieved successfully');
    }
}
