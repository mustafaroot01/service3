<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\ServiceResource;
use App\Models\Service;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $services = Service::visible()
            ->with('category')
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('category_id', $request->input('category_id')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $search = trim((string) $request->input('q'));
                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) $request->input('per_page', 20))
            ->appends($request->query());

        return ApiResponse::paginated($services, ServiceResource::class, 'Services retrieved successfully');
    }

    public function show(Service $service): JsonResponse
    {
        if (! $service->is_active || ! $service->category?->is_active) {
            return ApiResponse::notFound('الخدمة غير متاحة');
        }

        return ApiResponse::success(
            new ServiceResource($service->load('category')),
            'Service retrieved successfully'
        );
    }
}
