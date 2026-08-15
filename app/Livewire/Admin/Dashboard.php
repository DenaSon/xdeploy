<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('داشبورد مدیریت')]
final class Dashboard extends Component
{
    public function render(): View
    {
        $paidOrdersQuery = Order::query()
            ->whereHas(
                'payments',
                fn ($query) => $query
                    ->where('status', PaymentStatus::Paid->value)
                    ->whereNotNull('verified_at'),
            );

        $accounting = (clone $paidOrdersQuery)
            ->selectRaw(
                'COUNT(*) as paid_orders_count,
                 COALESCE(SUM(final_amount), 0) as gross_sales,
                 COALESCE(SUM(provider_cost), 0) as provider_cost,
                 COALESCE(SUM(final_amount), 0) - COALESCE(SUM(provider_cost), 0) as markup_profit,
                 COALESCE(AVG(markup_percent), 0) as average_markup_percent',
            )
            ->first();

        return view(
            'livewire.admin.dashboard',
            [
                'totalUsers' => User::query()->count(),
                'totalServers' => Server::query()->count(),
                'activeServers' => Server::query()
                    ->where('status', ServerStatus::Active->value)
                    ->count(),
                'totalOrders' => Order::query()->count(),
                'financial' => [
                    'paidOrdersCount' => (int) ($accounting?->paid_orders_count ?? 0),
                    'grossSales' => (int) ($accounting?->gross_sales ?? 0),
                    'providerCost' => (int) ($accounting?->provider_cost ?? 0),
                    'markupProfit' => (int) ($accounting?->markup_profit ?? 0),
                    'averageMarkupPercent' => (float) ($accounting?->average_markup_percent ?? 0),
                ],
                'recentUsers' => User::query()
                    ->with('profile')
                    ->withCount('servers')
                    ->addSelect([
                        'orders_count' => Order::query()
                            ->selectRaw('COUNT(*)')
                            ->whereColumn('orders.user_id', 'users.id'),
                    ])
                    ->latest()
                    ->limit(10)
                    ->get(),
                'recentOrders' => Order::query()
                    ->with('user.profile')
                    ->latest()
                    ->limit(10)
                    ->get(),
                'recentServers' => Server::query()
                    ->with('user.profile')
                    ->latest()
                    ->limit(10)
                    ->get(),
            ],
        );
    }
}
