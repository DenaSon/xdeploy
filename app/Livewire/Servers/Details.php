<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('اطلاعات سرور')]
final class Details extends Component
{
    #[Locked]
    public int $serverId;

    public function mount(Server $server): void
    {
        $ownedServer = $this
            ->authenticatedUser()
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        $this->serverId = (int) $ownedServer->getKey();
    }

    public function render(): View
    {
        $server = $this->ownedServer();

        return view(
            'livewire.servers.details',
            [
                'server' => $server,
                'sshCommand' => $this->sshCommand($server),
                'orders' => $this->recentOrders($server),
                'canRenew' => $this->canRenew($server),
            ],
        );
    }

    private function ownedServer(): Server
    {
        return $this
            ->authenticatedUser()
            ->servers()
            ->whereKey(
                $this->serverId,
            )
            ->firstOrFail();
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }

    private function sshCommand(Server $server): ?string
    {
        $host = trim(
            (string) $server->host,
        );

        $username = trim(
            (string) $server->username,
        );

        if ($host === '' || $username === '') {
            return null;
        }

        return sprintf(
            'ssh %s@%s -p %d',
            $username,
            $host,
            $server->port,
        );
    }

    private function canRenew(Server $server): bool
    {
        return $server->isCloudProvisioned()
            && $server->expires_at !== null
            && $server->termination_started_at === null
            && ! $server->isTerminated();
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     type_label: string,
     *     period_label: string,
     *     status_label: string,
     *     status_tone: string,
     *     amount_label: string,
     *     date: mixed,
     *     reference: string|null,
     *     verified: bool
     * }>
     */
    private function recentOrders(Server $server): Collection
    {
        return Order::query()
            ->where(
                'user_id',
                $server->user_id,
            )
            ->where(
                'server_id',
                $server->getKey(),
            )
            ->with([
                'payments' => static fn ($query) => $query
                    ->latest('id'),
            ])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(
                function (Order $order): array {
                    $paidPayment = $order
                        ->payments
                        ->first(
                            static fn ($payment): bool => $payment->status
                                === PaymentStatus::Paid,
                        );

                    $reference = $paidPayment?->gateway_reference;

                    return [
                        'id' => (int) $order->getKey(),

                        'type_label' => $this->orderTypeLabel(
                            $order->type,
                        ),

                        'period_label' => $this->periodLabel(
                            $order->period,
                        ),

                        'status_label' => $this->orderStatusLabel(
                            $order->status,
                        ),

                        'status_tone' => $this->orderStatusTone(
                            $order->status,
                        ),

                        'amount_label' => $this->amountLabel(
                            amount: (int) $order->final_amount,
                            currency: is_string($order->currency)
                                ? $order->currency
                                : null,
                        ),

                        'date' => $order->paid_at
                            ?? $paidPayment?->verified_at
                                ?? $order->created_at,

                        'reference' => is_string($reference)
                        && trim($reference) !== ''
                            ? $reference
                            : null,

                        'verified' => $paidPayment !== null
                            && $paidPayment->verified_at !== null,
                    ];
                },
            );
    }

    private function orderTypeLabel(
        OrderType $type,
    ): string {
        return match ($type) {
            OrderType::Provisioning => 'خرید سرور',
            OrderType::Renewal => 'تمدید سرویس',
        };
    }

    private function orderStatusLabel(
        OrderStatus $status,
    ): string {
        return match ($status) {
            OrderStatus::PendingPayment => 'در انتظار پرداخت',
            OrderStatus::Paid => 'پرداخت‌شده',
            OrderStatus::Provisioning => 'در حال آماده‌سازی',
            OrderStatus::Fulfilled => 'تکمیل‌شده',
            OrderStatus::Failed => 'ناموفق',
            OrderStatus::Cancelled => 'لغوشده',
            OrderStatus::Expired => 'منقضی‌شده',
        };
    }

    private function orderStatusTone(
        OrderStatus $status,
    ): string {
        return match ($status) {
            OrderStatus::Paid,
            OrderStatus::Fulfilled => 'success',

            OrderStatus::PendingPayment => 'warning',

            OrderStatus::Provisioning => 'info',

            OrderStatus::Failed => 'error',

            OrderStatus::Cancelled,
            OrderStatus::Expired => 'neutral',
        };
    }

    private function periodLabel(
        mixed $period,
    ): string {
        if (
            ! is_string($period)
            || trim($period) === ''
        ) {
            return '—';
        }

        $label = config(
            "money.periods.{$period}.label",
        );

        return is_string($label)
        && trim($label) !== ''
            ? $label
            : '—';
    }

    private function amountLabel(
        int $amount,
        ?string $currency,
    ): string {
        $currency = strtoupper(
            trim(
                $currency
                    ?: (string) config(
                        'money.currency',
                        'IRR',
                    ),
            ),
        );

        if ($currency === 'IRR') {
            return sprintf(
                '%s تومان',
                number_format(
                    (int) round(
                        $amount / 10,
                    ),
                ),
            );
        }

        return sprintf(
            '%s %s',
            number_format($amount),
            $currency,
        );
    }
}
