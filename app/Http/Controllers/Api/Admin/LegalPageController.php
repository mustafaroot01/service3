<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateLegalPageRequest;
use App\Http\Resources\Api\Admin\LegalPageResource;
use App\Models\LegalPage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LegalPageController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(
            LegalPageResource::collection(LegalPage::orderBy('id')->get())->resolve(),
            'Legal pages retrieved successfully'
        );
    }

    public function show(string $key): JsonResponse
    {
        $page = LegalPage::where('key', $key)->firstOrFail();

        return ApiResponse::success(new LegalPageResource($page), 'Legal page retrieved successfully');
    }

    public function update(UpdateLegalPageRequest $request, string $key): JsonResponse
    {
        $page = LegalPage::where('key', $key)->firstOrFail();
        $page->update($request->validated());

        return ApiResponse::success(new LegalPageResource($page->refresh()), 'Legal page updated successfully');
    }
}
