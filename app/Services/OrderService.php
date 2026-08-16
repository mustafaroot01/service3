<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\OrderStatus;
use App\Events\OrderStatusChanged;
use App\Events\OrderTechnicianReassigned;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Technician;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService extends BaseCrudService
{
    protected string $modelClass = Order::class;

    protected array $searchable = ['order_number', 'description'];

    protected array $sortable = ['id', 'order_number', 'status', 'scheduled_date', 'created_at'];

    protected string $defaultSort = 'created_at';

    protected array $filterable = [
        'status', 'governorate_id', 'district_id', 'service_id', 'technician_id', 'user_id',
    ];

    protected function baseQuery(): Builder
    {
        return $this->query()->with(['user', 'service.category', 'technician.specializations', 'governorate', 'district']);
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('unassigned')) {
            $request->boolean('unassigned')
                ? $query->whereNull('technician_id')
                : $query->whereNotNull('technician_id');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_date', '<=', $request->input('date_to'));
        }
    }

    public function hydrate(Model $model): Model
    {
        $model->load([
            'user', 'service.category', 'technician.specializations', 'governorate', 'district',
            'images', 'statusHistories',
        ]);

        OrderStatusHistory::primeActorNames($model->statusHistories);

        return $model;
    }

    /**
     * Lists one owner's orders — a technician's or a customer's — through the
     * same shape and filters as the index, so both nested tables behave alike.
     */
    public function paginateRelated(HasMany $orders, Request $request): LengthAwarePaginator
    {
        return $orders
            ->with(['user', 'service', 'technician', 'governorate', 'district'])
            ->when(
                trim((string) $request->input('q')) !== '',
                fn (Builder $query) => $query->where('order_number', 'like', '%'.trim((string) $request->input('q')).'%')
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', $request->input('status'))
            )
            ->latest()
            ->paginate($this->perPage($request))
            ->appends($request->query());
    }

    public function confirm(Order $order): Order
    {
        return $this->transition($order, OrderStatus::CONFIRMED);
    }

    public function assignTechnician(Order $order, int $technicianId): Order
    {
        $technician = Technician::assignableTo($order->governorate_id)->find($technicianId);

        if (! $technician) {
            throw ValidationException::withMessages([
                'technician_id' => 'يجب اختيار فني نشط من نفس محافظة الطلب',
            ]);
        }

        return $this->transition(
            $order,
            OrderStatus::ASSIGNED,
            "تم تعيين الفني: {$technician->name}",
            ['technician_id' => $technician->id]
        );
    }

    /**
     * Swaps the technician on an order that already has one, without touching
     * its status. Every check runs under the row lock, because another admin
     * may complete or re-assign the same order while this request is in flight.
     */
    public function reassignTechnician(Order $order, int $technicianId): Order
    {
        $actor = $this->actor();
        $previous = null;
        $replacement = null;

        DB::transaction(function () use ($order, $technicianId, $actor, &$previous, &$replacement) {
            $fresh = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->technician_id === null) {
                throw ValidationException::withMessages([
                    'technician_id' => 'لم يُعيَّن فني بعد، استخدم تعيين فني',
                ]);
            }

            if ($fresh->status->isFinal()) {
                throw ValidationException::withMessages([
                    'technician_id' => "الطلب في حالة نهائية ({$fresh->status->label()}) ولا يمكن تغيير الفني",
                ]);
            }

            if ($fresh->technician_id === $technicianId) {
                throw ValidationException::withMessages([
                    'technician_id' => 'هذا الفني معيَّن على الطلب بالفعل',
                ]);
            }

            $replacement = Technician::assignableTo($fresh->governorate_id)->find($technicianId);

            if (! $replacement) {
                throw ValidationException::withMessages([
                    'technician_id' => 'يجب اختيار فني نشط من نفس محافظة الطلب',
                ]);
            }

            $previous = Technician::findOrFail($fresh->technician_id);

            $fresh->forceFill(['technician_id' => $replacement->id])->save();

            $fresh->statusHistories()->create([
                'from_status' => $fresh->status,
                'to_status' => $fresh->status,
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'note' => "تم استبدال الفني: {$previous->name} ← {$replacement->name}",
                'created_at' => now(),
            ]);

            $order->setRawAttributes($fresh->getAttributes(), true);
        });

        $this->hydrate($order);

        OrderTechnicianReassigned::dispatch($order, $previous, $replacement);

        return $order;
    }

    public function inspect(Order $order, string $note): Order
    {
        return $this->transition($order, OrderStatus::INSPECTED, $note, ['inspection_note' => $note]);
    }

    public function complete(Order $order): Order
    {
        return $this->transition($order, OrderStatus::COMPLETED);
    }

    public function cancel(Order $order, ?string $note = null): Order
    {
        $actor = $this->actor();

        return $this->transition($order, OrderStatus::CANCELLED, $note, [
            'cancelled_by' => $actor['type']->value,
            'cancelled_at' => now(),
        ]);
    }

    public function transition(Order $order, OrderStatus $to, ?string $note = null, array $extra = []): Order
    {
        $actor = $this->actor();
        $from = $order->status;

        DB::transaction(function () use ($order, $to, $note, $extra, $actor, &$from) {
            $fresh = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $from = $fresh->status;

            if ($to === OrderStatus::CANCELLED
                && $actor['type'] === ActorType::USER
                && ! $from->isCancellableByCustomer()) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء الطلب بعد تأكيده',
                ]);
            }

            if (! $from->canMoveTo($to)) {
                throw ValidationException::withMessages([
                    'status' => $from->isFinal()
                        ? "الطلب في حالة نهائية ({$from->label()}) ولا يمكن تغييرها"
                        : $this->rejectionMessage($from, $to),
                ]);
            }

            $fresh->forceFill($extra + ['status' => $to])->save();

            $fresh->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $actor['type'],
                'actor_id' => $actor['id'],
                'note' => $note,
                'created_at' => now(),
            ]);

            $order->setRawAttributes($fresh->getAttributes(), true);
        });

        $this->hydrate($order);

        OrderStatusChanged::dispatch($order, $from, $to, $note);

        return $order;
    }

    private function rejectionMessage(OrderStatus $from, OrderStatus $to): string
    {
        $allowed = collect($from->allowedNext())
            ->map(fn (OrderStatus $status) => $status->label())
            ->implode(' أو ');

        return "لا يمكن الانتقال من ({$from->label()}) إلى ({$to->label()}) — المسموح: {$allowed}";
    }

    /**
     * @return array{type: ActorType, id: int|null}
     */
    private function actor(): array
    {
        if ($admin = Auth::guard('admin')->user()) {
            return ['type' => ActorType::ADMIN, 'id' => $admin->getKey()];
        }

        if ($user = Auth::guard('user')->user()) {
            return ['type' => ActorType::USER, 'id' => $user->getKey()];
        }

        return ['type' => ActorType::SYSTEM, 'id' => null];
    }
}
