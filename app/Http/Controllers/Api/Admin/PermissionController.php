<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Admin\PermissionResource;
use App\Services\PermissionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->permissions->list($request),
            PermissionResource::class,
            'Permissions retrieved successfully'
        );
    }

    public function filters(): JsonResponse
    {
        return ApiResponse::success($this->permissions->filters(), 'Permission filters retrieved successfully');
    }
}
