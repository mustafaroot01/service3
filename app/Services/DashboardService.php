<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TechnicianStatus;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\Technician;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function summary(): array
    {
        return [
            'orders' => $this->orderSummary(),
            'people' => $this->peopleSummary(),
            'catalog' => $this->catalogSummary(),
            'top_services' => $this->topServices(),
            'orders_per_governorate' => $this->ordersPerGovernorate(),
            'recent_orders' => $this->recentOrders(),
        ];
    }

    private function orderSummary(): array
    {
        $byStatus = Order::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = [];
        foreach (OrderStatus::cases() as $status) {
            $statuses[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($byStatus[$status->value] ?? 0),
            ];
        }

        return [
            'total' => (int) $byStatus->sum(),
            'open' => Order::open()->count(),
            'unassigned' => Order::open()->unassigned()->count(),
            'today' => Order::whereDate('created_at', Carbon::today())->count(),
            'this_month' => Order::whereBetween('created_at', [
                Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(),
            ])->count(),
            'by_status' => $statuses,
            'trend' => $this->ordersTrend(),
        ];
    }

    /** Daily totals with the empty days filled in, so the chart draws a continuous line. */
    private function ordersTrend(int $days = 14): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $totals = Order::selectRaw('date(created_at) as day, count(*) as total')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('total', 'day');

        $trend = [];
        foreach (CarbonPeriod::create($from, Carbon::today()) as $day) {
            $date = $day->toDateString();
            $trend[] = ['date' => $date, 'total' => (int) $totals->get($date, 0)];
        }

        return $trend;
    }

    private function peopleSummary(): array
    {
        return [
            'users_total' => User::count(),
            'users_active' => User::where('status', UserStatus::ACTIVE)->count(),
            'users_unverified' => User::whereNull('phone_verified_at')->count(),
            'technicians_total' => Technician::count(),
            'technicians_active' => Technician::where('status', TechnicianStatus::ACTIVE)->count(),
            'technicians_pending' => Technician::where('status', TechnicianStatus::PENDING)->count(),
        ];
    }

    private function catalogSummary(): array
    {
        return [
            'categories' => Category::count(),
            'categories_active' => Category::where('is_active', true)->count(),
            'services' => Service::count(),
            'services_active' => Service::where('is_active', true)->count(),
        ];
    }

    private function topServices(int $limit = 5): array
    {
        return Service::withCount('orders')
            ->having('orders_count', '>', 0)
            ->orderByDesc('orders_count')
            ->limit($limit)
            ->get()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'orders_count' => $service->orders_count,
            ])
            ->all();
    }

    private function ordersPerGovernorate(): array
    {
        return Order::selectRaw('governorates.id, governorates.name, count(orders.id) as total')
            ->join('governorates', 'governorates.id', '=', 'orders.governorate_id')
            ->groupBy('governorates.id', 'governorates.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    private function recentOrders(int $limit = 8): array
    {
        return Order::with(['user', 'service'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'user_name' => $order->user?->name,
                'service_name' => $order->service?->name,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
