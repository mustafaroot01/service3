<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\SaveRoleRequest;
use App\Services\RoleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->roles->list(), 'Roles retrieved successfully');
    }

    public function permissions(): JsonResponse
    {
        return ApiResponse::success($this->roles->catalog(), 'Permissions retrieved successfully');
    }

    public function store(SaveRoleRequest $request): JsonResponse
    {
        return ApiResponse::created(
            $this->roles->create($request->validated()),
            'تم إنشاء الدور بنجاح'
        );
    }

    public function update(SaveRoleRequest $request, Role $role): JsonResponse
    {
        return ApiResponse::success(
            $this->roles->update($role, $request->validated()),
            'تم تحديث الدور بنجاح'
        );
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roles->delete($role);

        return ApiResponse::success(null, 'تم حذف الدور بنجاح');
    }
}
