<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Application\Billing\Actions\ListCustomerOrdersAction;
use App\Application\Billing\Actions\ResolveCustomerOrderStateAction;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Billing\CustomerOrderPresentation;
use App\Support\Cloud\CloudRegionLabel;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('سفارش‌ها')]
final class Index extends Component
{
    use WithPagination;

    public function render(
        ListCustomerOrdersAction $listOrders,
        ResolveCustomerOrderStateAction $resolveState,
    ): View {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $orders = $listOrders->execute(
            user: $user,
            perPage: 12,
        );

        /** @var array<int, array<string, mixed>> $presentation */
        $presentation = [];

        foreach ($orders->getCollection() as $order) {
            if (! $order instanceof Order) {
                continue;
            }

            $latestPayment = $order->latestPayment;

            $paid = $order->paid_at !== null
                || $latestPayment?->status === PaymentStatus::Paid;

            $presentation[(int) $order->getKey()] = [
                'state' => CustomerOrderPresentation::state(
                    state: $resolveState->execute($order),
                    paid: $paid,
                ),
                'payment' => CustomerOrderPresentation::payment(
                    $latestPayment?->status,
                ),
                'type' => CustomerOrderPresentation::type(
                    $order->type,
                ),
                'provider' => CustomerOrderPresentation::provider(
                    $order->cloud_provider,
                ),
                'region' => CloudRegionLabel::for(
                    $order->region_id,
                ),
                'amount' => MoneyFormatter::tomanFromRial(
                    $order->final_amount,
                ),
                'period' => $this->periodLabel(
                    $order->period,
                ),
            ];
        }

        return view(
            'livewire.orders.index',
            [
                'orders' => $orders,
                'presentation' => $presentation,
            ],
        )->layout(
            'layouts.panel',
        );
    }

    private function periodLabel(string $period): string
    {
        $label = config(
            "money.periods.{$period}.label",
        );

        return is_string($label)
            ? $label
            : $period;
    }
}
