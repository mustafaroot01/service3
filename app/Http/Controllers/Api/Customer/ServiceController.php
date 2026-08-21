<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\ServiceResource;
use App\Http\Resources\Api\Customer\ServiceSuggestionResource;
use App\Models\Service;
use App\Support\ApiResponse;
use App\Support\Pagination;
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
            ->when($request->filled('q'), fn (Builder $q) => $q->search((string) $request->input('q'), withDescription: true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(Pagination::perPage($request, 20))
            ->appends($request->query());

        return ApiResponse::paginated($services, ServiceResource::class, 'Services retrieved successfully');
    }

    /**
     * Lightweight autocomplete for the app's search box: a short, relevance-ranked
     * list of visible services as the customer types. An empty query returns an
     * empty list rather than the whole catalogue.
     */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        if ($term === '') {
            return ApiResponse::success([], 'Suggestions retrieved successfully');
        }

        $limit = max(1, min((int) $request->input('limit', 8), 15));

        $services = Service::visible()
            ->search($term)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return ApiResponse::success(
            ServiceSuggestionResource::collection($services)->resolve(),
            'Suggestions retrieved successfully'
        );
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
