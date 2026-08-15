<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('سفارش‌ها')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = 'all';

    #[Url(history: true)]
    public string $type = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $statuses = array_column(OrderStatus::cases(), 'value');
        $types = array_column(OrderType::cases(), 'value');

        $orders = Order::query()
            ->with(['user.profile', 'historicalServer'])
            ->withCount('payments')
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(
                        function (Builder $query) use ($search): void {
                            if (ctype_digit($search)) {
                                $query->orWhere('id', (int) $search);
                            }

                            $query
                                ->orWhereHas(
                                    'user',
                                    fn (Builder $userQuery) => $userQuery
                                        ->matchesIdentity($search),
                                )
                                ->orWhereHas(
                                    'historicalServer',
                                    fn (Builder $serverQuery) => $serverQuery
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('host', 'like', "%{$search}%"),
                                );
                        },
                    );
                },
            )
            ->when(
                in_array($this->status, $statuses, true),
                fn (Builder $query) => $query->where('status', $this->status),
            )
            ->when(
                in_array($this->type, $types, true),
                fn (Builder $query) => $query->where('type', $this->type),
            )
            ->latest('id')
            ->paginate(20);

        return view(
            'livewire.admin.orders.index',
            ['orders' => $orders],
        );
    }
}
