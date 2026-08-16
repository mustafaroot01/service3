<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\NotificationResource;
use App\Models\Notification;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $this->scoped($request)
            ->when($request->boolean('unread'), fn (Builder $q) => $q->whereNull('read_at'))
            ->latest('id')
            ->paginate(Pagination::perPage($request, 20))
            ->appends($request->query());

        return ApiResponse::paginated($notifications, NotificationResource::class, 'Notifications retrieved successfully');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(
            ['unread_count' => $this->scoped($request)->whereNull('read_at')->count()],
            'Unread count retrieved successfully'
        );
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $row = $this->scoped($request)->findOrFail((int) $notification);
        $row->forceFill(['read_at' => $row->read_at ?? now()])->save();

        return ApiResponse::success(new NotificationResource($row), 'تم تعليم الإشعار كمقروء');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->scoped($request)->whereNull('read_at')->update(['read_at' => now()]);

        return ApiResponse::success(['marked' => $updated], 'تم تعليم كل الإشعارات كمقروءة');
    }

    private function scoped(Request $request): Builder
    {
        $user = $request->user();

        return Notification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }
}
