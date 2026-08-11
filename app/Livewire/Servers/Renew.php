<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Billing\Actions\CalculateCloudRenewalPriceAction;
use App\Application\Billing\Actions\CreatePaymentAction;
use App\Application\Billing\Actions\CreateRenewalOrderAction;
use App\Domain\Billing\DTOs\PurchasePriceData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Domain\Billing\Exceptions\OrderNotPayableException;
use App\Domain\Billing\Exceptions\OrderQuoteExpiredException;
use App\Domain\Billing\Exceptions\PaymentInitiationInProgressException;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use App\Support\Cloud\CloudRegionLabel;
use App\Support\Money\MoneyFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;
use Throwable;

#[Title('تمدید سرویس')]
final class Renew extends Component
{
    use Toast;

    #[Locked]
    public int $serverId;

    /**
     * @var list<array{
     *     id: string,
     *     label: string,
     *     hint: string,
     *     recommended: bool
     * }>
     */
    public array $periods = [];

    /**
     * @var array{
     *     final_amount: int,
     *     currency: string,
     *     duration_hours: int
     * }|array{}
     */
    public array $quote = [];

    public string $period = '';

    public bool $quoteLoaded = false;

    public ?int $pendingOrderId = null;

    public ?string $quoteError = null;

    public string $paymentResult = '';

    public function mount(
        Server $server,
    ): void {
        $ownedServer = $this->authenticatedUser()
            ->servers()
            ->whereKey($server->getKey())
            ->firstOrFail();

        $this->serverId = (int) $ownedServer->getKey();

        $paymentResult = trim(
            (string) request()->query('payment', ''),
        );

        $this->paymentResult = in_array(
            $paymentResult,
            ['success', 'cancelled'],
            true,
        )
            ? $paymentResult
            : '';

        $this->loadPeriods();
    }

    public function loadQuote(): void
    {
        if ($this->quoteLoaded) {
            return;
        }

        $this->recalculateQuote();
        $this->quoteLoaded = true;
    }

    public function selectPeriod(
        string $period,
    ): void {
        if ($this->findPeriod($period) === null) {
            return;
        }

        if ($this->period === $period) {
            return;
        }

        $this->period = $period;
        $this->pendingOrderId = null;

        $this->recalculateQuote();
        $this->quoteLoaded = true;
    }

    public function renew(
        CreateRenewalOrderAction $createRenewalOrder,
        CreatePaymentAction $createPayment,
    ): mixed {
        if ($this->findPeriod($this->period) === null) {
            $this->error(
                'دوره تمدید مشخص نیست',
                'لطفاً یکی از دوره‌های تمدید را انتخاب کنید.',
            );

            return null;
        }

        if ($this->quote === []) {
            $this->recalculateQuote();

            if ($this->quote === []) {
                $this->error(
                    'قیمت تمدید در دسترس نیست',
                    'در حال حاضر امکان دریافت قیمت تمدید وجود ندارد. دوباره تلاش کنید.',
                );

                return null;
            }
        }

        $user = $this->authenticatedUser();

        try {
            if ($this->pendingOrderId === null) {
                $order = $createRenewalOrder->execute(
                    user: $user,
                    serverId: $this->serverId,
                    period: $this->period,
                );

                $this->pendingOrderId = (int) $order->getKey();
            }

            $payment = $createPayment->execute(
                user: $user,
                orderId: $this->pendingOrderId,
                callbackUrl: route(
                    'payments.zarinpal.callback',
                ),
            );

            return redirect()->away(
                $payment->redirectUrl,
            );
        } catch (OrderQuoteExpiredException) {
            $this->pendingOrderId = null;
            $this->recalculateQuote();

            $this->warning(
                'پیش‌فاکتور منقضی شد',
                'قیمت تمدید به‌روزرسانی شد. لطفاً پرداخت را دوباره آغاز کنید.',
            );

            return null;
        } catch (PaymentInitiationInProgressException) {
            $this->info(
                'پرداخت در حال آماده‌سازی است',
                'لطفاً چند لحظه دیگر دوباره تلاش کنید.',
            );

            return null;
        } catch (OrderNotPayableException) {
            $this->pendingOrderId = null;

            $this->warning(
                'سفارش قابل پرداخت نیست',
                'وضعیت سفارش تمدید تغییر کرده است. لطفاً دوباره تلاش کنید.',
            );

            return null;
        } catch (CloudServerRenewalException $exception) {
            report($exception);

            $this->pendingOrderId = null;
            $this->quote = [];
            $this->quoteError =
                'این سرویس در وضعیت فعلی قابل تمدید نیست.';

            $this->error(
                'تمدید امکان‌پذیر نیست',
                'وضعیت سرویس تغییر کرده است. صفحه را به‌روزرسانی کنید.',
            );

            return null;
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'شروع پرداخت ناموفق بود',
                'سفارش تمدید شما حفظ شده است. لطفاً چند لحظه دیگر دوباره تلاش کنید.',
            );

            return null;
        }
    }

    public function render(): View
    {
        $server = $this->ownedServer();
        $sourceOrder = $this->sourceOrder($server);

        $canRenew = $this->canRenew(
            server: $server,
            sourceOrder: $sourceOrder,
        );

        $projectedExpiresAt = null;

        if (
            $canRenew
            && $server->expires_at !== null
            && isset($this->quote['duration_hours'])
        ) {
            $projectedExpiresAt = $server->expires_at->addHours(
                (int) $this->quote['duration_hours'],
            );
        }

        return view(
            'livewire.servers.renew',
            [
                'server' => $server,
                'sourceOrder' => $sourceOrder,
                'canRenew' => $canRenew,
                'selectedPeriod' => $this->findPeriod(
                    $this->period,
                ),
                'regionLabel' => $sourceOrder instanceof Order
                    ? CloudRegionLabel::for($sourceOrder->region_id)
                    : '—',
                'currentExpiresAt' => $server->expires_at,
                'projectedExpiresAt' => $projectedExpiresAt,
                'remainingLabel' => $this->remainingLabel($server),
                'isExpiringSoon' => $this->isExpiringSoon($server),
                'paymentResult' => $this->paymentResult,
                'quoteTtlMinutes' => max(
                    1,
                    (int) config(
                        'money.quote_ttl_minutes',
                        15,
                    ),
                ),
            ],
        )->layout(
            'layouts.panel',
        );
    }

    public function formatToman(
        int $rialAmount,
    ): string {
        return MoneyFormatter::tomanFromRial(
            $rialAmount,
        );
    }

    private function recalculateQuote(): void
    {
        $this->quote = [];
        $this->quoteError = null;

        if ($this->findPeriod($this->period) === null) {
            return;
        }

        try {
            $price = app(
                CalculateCloudRenewalPriceAction::class,
            )->execute(
                user: $this->authenticatedUser(),
                serverId: $this->serverId,
                period: $this->period,
            );

            $this->quote = $this->quoteArray($price);
        } catch (CloudServerRenewalException) {
            $this->quoteError =
                'این سرویس در وضعیت فعلی قابل تمدید نیست.';
        } catch (Throwable $exception) {
            report($exception);

            $this->quoteError =
                'دریافت قیمت تمدید ناموفق بود. دوباره تلاش کنید.';
        }
    }

    /**
     * @return array{
     *     final_amount: int,
     *     currency: string,
     *     duration_hours: int
     * }
     */
    private function quoteArray(
        PurchasePriceData $price,
    ): array {
        return [
            'final_amount' => (int) $price->finalAmount,
            'currency' => $price->currency,
            'duration_hours' => $price->durationHours,
        ];
    }

    private function loadPeriods(): void
    {
        $this->periods = [];

        foreach ((array) config('money.periods', []) as $id => $config) {
            if (
                ! is_string($id)
                || ! is_array($config)
                || ! is_string($config['label'] ?? null)
            ) {
                continue;
            }

            $this->periods[] = [
                'id' => $id,
                'label' => $config['label'],
                'hint' => $this->periodHint($id),
                'recommended' => $id === '14_days',
            ];
        }

        $preferred = $this->findPeriod('14_days');

        $this->period = $preferred['id']
            ?? ($this->periods[0]['id'] ?? '');
    }

    /**
     * @return array{id: string, label: string, hint: string, recommended: bool}|null
     */
    private function findPeriod(
        string $period,
    ): ?array {
        foreach ($this->periods as $item) {
            if ($item['id'] === $period) {
                return $item;
            }
        }

        return null;
    }

    private function periodHint(
        string $period,
    ): string {
        return match ($period) {
            '2_days' => 'تمدید کوتاه‌مدت',
            '14_days' => 'پیشنهاد xDeploy',
            '1_month' => 'تمدید ماهانه',
            default => 'دوره تمدید',
        };
    }

    private function ownedServer(): Server
    {
        /** @var Server $server */
        $server = $this->authenticatedUser()
            ->servers()
            ->whereKey($this->serverId)
            ->firstOrFail();

        return $server;
    }

    private function sourceOrder(
        Server $server,
    ): ?Order {
        /** @var Order|null $order */
        $order = Order::query()
            ->where('user_id', $server->user_id)
            ->where('server_id', $server->getKey())
            ->where('type', OrderType::Provisioning)
            ->where('status', OrderStatus::Fulfilled)
            ->oldest('id')
            ->first();

        return $order;
    }

    private function canRenew(
        Server $server,
        ?Order $sourceOrder,
    ): bool {
        return $sourceOrder instanceof Order
            && $server->isCloudProvisioned()
            && $server->expires_at !== null
            && ! $server->hasExpired()
            && ! $server->isTerminated()
            && $server->termination_started_at === null;
    }

    private function isExpiringSoon(
        Server $server,
    ): bool {
        return $server->expires_at !== null
            && $server->expires_at->isFuture()
            && $server->expires_at->lessThanOrEqualTo(
                now()->addHours(24),
            );
    }

    private function remainingLabel(
        Server $server,
    ): string {
        if ($server->expires_at === null) {
            return '—';
        }

        $seconds = max(
            0,
            (int) now()->diffInSeconds(
                $server->expires_at,
                false,
            ),
        );

        if ($seconds === 0) {
            return 'پایان یافته';
        }

        $hours = intdiv($seconds, 3600);
        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        if ($days > 0) {
            return sprintf(
                '%d روز و %d ساعت',
                $days,
                $remainingHours,
            );
        }

        return sprintf(
            '%d ساعت',
            max(1, $hours),
        );
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
}
