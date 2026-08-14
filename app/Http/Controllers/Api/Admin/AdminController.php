<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Requests\Api\Admin\SaveAdminRequest;
use App\Http\Requests\Api\Admin\UpdateAdminStatusRequest;
use App\Http\Resources\Api\Admin\AdminResource;
use App\Models\Admin;
use App\Services\AdminService;
use App\Services\BaseCrudService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminController extends AdminCrudController
{
    public function __construct(private readonly AdminService $admins)
    {
    }

    protected function service(): BaseCrudService
    {
        return $this->admins;
    }

    protected function resource(): string
    {
        return AdminResource::class;
    }

    protected function label(): string
    {
        return 'Admin';
    }

    public function store(SaveAdminRequest $request): JsonResponse
    {
        return ApiResponse::created(
            new AdminResource($this->admins->create($request->validated())),
            'تم إنشاء حساب المشرف بنجاح'
        );
    }

    public function update(SaveAdminRequest $request, Admin $admin): JsonResponse
    {
        return ApiResponse::success(
            new AdminResource($this->admins->update($admin, $request->validated())),
            'تم تحديث حساب المشرف بنجاح'
        );
    }

    public function changeStatus(UpdateAdminStatusRequest $request, Admin $admin): JsonResponse
    {
        return ApiResponse::success(
            new AdminResource($this->admins->changeStatus($admin, $request->status())),
            'تم تحديث حالة الحساب بنجاح'
        );
    }
}
