<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Payments;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('پرداخت‌ها')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = 'all';

    #[Url(history: true)]
    public string $gateway = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedGateway(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $statuses = array_column(PaymentStatus::cases(), 'value');

        $payments = Payment::query()
            ->with('order.user')
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(
                        function (Builder $query) use ($search): void {
                            if (ctype_digit($search)) {
                                $query->orWhere('order_id', (int) $search);
                            }

                            $query
                                ->orWhere('gateway_reference', 'like', "%{$search}%")
                                ->orWhere('gateway_transaction_id', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'order.user',
                                    fn (Builder $userQuery) => $userQuery
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('phone', 'like', "%{$search}%"),
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
                $this->gateway !== 'all' && $this->gateway !== '',
                fn (Builder $query) => $query->where('gateway', $this->gateway),
            )
            ->latest('id')
            ->paginate(20);

        return view(
            'livewire.admin.payments.index',
            [
                'payments' => $payments,
                'gateways' => Payment::query()
                    ->select('gateway')
                    ->distinct()
                    ->orderBy('gateway')
                    ->pluck('gateway'),
            ],
        );
    }
}
