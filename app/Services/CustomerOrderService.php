<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Support\VisitWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerOrderService
{
    private const LIST_RELATIONS = ['service.category', 'technician', 'governorate', 'district'];

    private const DETAIL_RELATIONS = [
        'service.category', 'technician', 'governorate', 'district', 'images', 'statusHistories',
    ];

    public function __construct(private readonly OrderService $orders) {}

    public function create(User $user, array $data): Order
    {
        if (! $user->governorate_id || ! $user->district_id) {
            throw ValidationException::withMessages([
                'governorate_id' => 'أكمل بيانات محافظتك وقضائك من الملف الشخصي قبل الطلب',
            ]);
        }

        $images = array_values(array_filter(
            $data['images'] ?? [],
            fn ($file) => $file instanceof UploadedFile
        ));

        $attributes = [
            'user_id' => $user->id,
            'service_id' => $data['service_id'],
            'governorate_id' => $user->governorate_id,
            'district_id' => $user->district_id,
            'description' => $data['description'],
            'scheduled_date' => VisitWindow::date(),
            'time_from' => $data['time_from'],
            'time_to' => $data['time_to'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'landmark' => $data['landmark'] ?? null,
        ];

        $written = [];

        try {
            $order = DB::transaction(function () use ($user, $attributes, $images, &$written) {
                $order = Order::createWithNumber($attributes);

                foreach ($images as $index => $file) {
                    $path = $file->store("orders/{$order->id}", 'public');
                    $written[] = $path;

                    $order->images()->create(['path' => $path, 'sort' => $index]);
                }

                $order->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => OrderStatus::PENDING,
                    'actor_type' => ActorType::USER,
                    'actor_id' => $user->id,
                    'note' => 'تم استلام الطلب',
                    'created_at' => now(),
                ]);

                return $order;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($written);
            throw $e;
        }

        $order->refresh()->load(self::DETAIL_RELATIONS);

        OrderStatusChanged::dispatch($order, null, OrderStatus::PENDING);

        return $order;
    }

    public function paginateFor(User $user, Request $request): LengthAwarePaginator
    {
        return $this->scopedTo($user)
            ->with(self::LIST_RELATIONS)
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->latest('id')
            ->paginate((int) $request->input('per_page', 15))
            ->appends($request->query());
    }

    public function findFor(User $user, int $id): Order
    {
        return $this->scopedTo($user)
            ->with(self::DETAIL_RELATIONS)
            ->findOrFail($id);
    }

    public function cancel(User $user, int $id): Order
    {
        $order = $this->scopedTo($user)->findOrFail($id);

        return $this->orders->cancel($order)->load(self::DETAIL_RELATIONS);
    }

    /**
     * Every customer-facing query starts here, so an order can never be read
     * or mutated by anyone other than the customer who placed it.
     */
    private function scopedTo(User $user): Builder
    {
        return Order::query()->where('user_id', $user->id);
    }
}
