<?php

declare(strict_types=1);

namespace App\Livewire\Orders;

use App\Application\Billing\Actions\CreatePaymentAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\OrderNotPayableException;
use App\Domain\Billing\Exceptions\OrderQuoteExpiredException;
use App\Domain\Billing\Exceptions\PaymentInitiationInProgressException;
use App\Models\Order;
use App\Models\Payment;
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

#[Title('وضعیت سفارش')]
final class Show extends Component
{
    use Toast;

    #[Locked]
    public int $orderId;

    public function mount(
        Order $order,
    ): void {
        $user = $this->authenticatedUser();

        abort_unless(
            $order->user_id === $user->getKey(),
            404,
        );

        $this->orderId = $order->getKey();
    }

    public function refreshOrder(): void
    {
        /*
         * The method intentionally has no mutation.
         * Livewire polling invokes a fresh render, which reloads the
         * authoritative Order + Server state from the database.
         */
    }

    public function pay(
        CreatePaymentAction $createPayment,
    ): mixed {
        $order = $this->ownedOrder();

        if (
            $order->status
            !== OrderStatus::PendingPayment
        ) {
            $this->warning(
                'سفارش قابل پرداخت نیست',
                'وضعیت سفارش تغییر کرده است. صفحه به‌روزرسانی شد.',
            );

            return null;
        }

        try {
            $payment = $createPayment->execute(
                user: $this->authenticatedUser(),
                orderId: $order->getKey(),
                callbackUrl: route(
                    'payments.zarinpal.callback',
                ),
            );

            return redirect()->away(
                $payment->redirectUrl,
            );
        } catch (OrderQuoteExpiredException) {
            $this->warning(
                'پیش‌فاکتور منقضی شد',
                'برای دریافت قیمت جدید، یک سفارش تازه ایجاد کنید.',
            );

            return null;
        } catch (PaymentInitiationInProgressException) {
            $this->info(
                'پرداخت در حال آماده‌سازی است',
                'لطفاً چند لحظه صبر کنید و سپس دوباره تلاش کنید.',
            );

            return null;
        } catch (OrderNotPayableException) {
            $this->warning(
                'سفارش قابل پرداخت نیست',
                'وضعیت سفارش تغییر کرده است. صفحه به‌روزرسانی شد.',
            );

            return null;
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->error(
                'شروع پرداخت ناموفق بود',
                'سفارش شما حفظ شده است. لطفاً چند لحظه دیگر دوباره تلاش کنید.',
            );

            return null;
        }
    }

    public function render(): View
    {
        $order = $this->ownedOrder()
            ->load(
                'server',
            );

        $server = $order->server;

        $latestPayment =
            $order->payments()
                ->latest('id')
                ->first();

        $statusMeta = $this->statusMeta(
            order: $order,
            server: $server,
        );

        return view(
            'livewire.orders.show',
            [
                'order' => $order,
                'server' => $server,
                'latestPayment' => $latestPayment,

                'statusMeta' => $statusMeta,

                'periodLabel' => $this->periodLabel(
                    $order->period,
                ),

                'paymentNotice' => $this->paymentNotice(
                    order: $order,
                    payment: $latestPayment,
                ),

                'canPay' => $order->status
                    === OrderStatus::PendingPayment,

                'shouldPoll' => $this->shouldPoll(
                    order: $order,
                    server: $server,
                ),

                ...$this->presentationData(
                    order: $order,
                    server: $server,
                ),

            ],
        )->layout(
            'layouts.panel',
        );
    }

    private function ownedOrder(): Order
    {
        $user = $this->authenticatedUser();

        /** @var Order $order */
        $order = Order::query()
            ->whereKey(
                $this->orderId,
            )
            ->where(
                'user_id',
                $user->getKey(),
            )
            ->firstOrFail();

        return $order;
    }

    /**
     * @return array{
     *     label: string,
     *     description: string,
     *     icon: string,
     *     badge: string
     * }
     */
    private function statusMeta(
        Order $order,
        ?Server $server,
    ): array {
        if (
            $order->status
            === OrderStatus::Fulfilled
        ) {
            if (
                $server instanceof Server
                && $server->isActive()
            ) {
                return [
                    'label' => 'آماده استفاده',
                    'description' => 'سرور با موفقیت ساخته شده و اتصال آن نیز آماده استفاده است.',
                    'icon' => 'lucide.circle-check',
                    'badge' => 'badge-success',
                ];
            }

            return [
                'label' => 'VPS ساخته شد',
                'description' => 'ساخت VPS با موفقیت تکمیل شده است. در حال بررسی آمادگی اتصال هستیم.',
                'icon' => 'lucide.server',
                'badge' => 'badge-info',
            ];
        }

        return match ($order->status) {
            OrderStatus::PendingPayment => [
                'label' => 'در انتظار پرداخت',
                'description' => 'سفارش ثبت شده است. برای ادامه فرایند، لطفاً پرداخت را تکمیل کنید.',
                'icon' => 'lucide.credit-card',
                'badge' => 'badge-warning',
            ],

            OrderStatus::Paid => [
                'label' => 'پرداخت تأیید شد',
                'description' => 'پرداخت با موفقیت تأیید شده است و سفارش برای ساخت VPS در صف قرار دارد.',
                'icon' => 'lucide.badge-check',
                'badge' => 'badge-success',
            ],

            OrderStatus::Provisioning => [
                'label' => 'در حال ساخت VPS',
                'description' => 'درخواست ساخت VPS به ارائه‌دهنده زیرساخت ارسال شده است.',
                'icon' => 'lucide.loader-circle',
                'badge' => 'badge-info',
            ],

            OrderStatus::Failed => [
                'label' => 'ساخت سرور ناموفق بود',
                'description' => 'فرایند ساخت متوقف شده است. برای جلوگیری از ایجاد VPS تکراری، تلاش مجدد به‌صورت خودکار انجام نمی‌شود.',
                'icon' => 'lucide.triangle-alert',
                'badge' => 'badge-error',
            ],

            OrderStatus::Cancelled => [
                'label' => 'سفارش لغو شد',
                'description' => 'این سفارش لغو شده است و وارد فرایند ساخت VPS نخواهد شد.',
                'icon' => 'lucide.circle-x',
                'badge' => 'badge-neutral',
            ],

            OrderStatus::Expired => [
                'label' => 'پیش‌فاکتور منقضی شد',
                'description' => 'اعتبار قیمت این سفارش به پایان رسیده است. لطفاً یک سفارش جدید ایجاد کنید.',
                'icon' => 'lucide.clock-alert',
                'badge' => 'badge-warning',
            ],

            /*
             * Fulfilled is handled before this match because its
             * presentation also depends on Server readiness.
             */
            OrderStatus::Fulfilled => throw new \LogicException(
                'Fulfilled Order status was not handled.',
            ),
        };
    }

    /**
     * @return array{
     *     type: string,
     *     message: string
     * }|null
     */
    private function paymentNotice(
        Order $order,
        ?Payment $payment,
    ): ?array {
        if (
            $order->status
            !== OrderStatus::PendingPayment
            || ! $payment instanceof Payment
        ) {
            return null;
        }

        return match ($payment->status) {
            PaymentStatus::Cancelled => [
                'type' => 'warning',
                'message' => 'پرداخت قبلی لغو شده است. در صورت معتبر بودن پیش‌فاکتور، می‌توانید پرداخت را دوباره آغاز کنید.',
            ],

            PaymentStatus::Failed => [
                'type' => 'error',
                'message' => 'شروع پرداخت قبلی انجام نشد. سفارش شما حفظ شده است و می‌توانید دوباره تلاش کنید.',
            ],

            PaymentStatus::Initiating => [
                'type' => 'info',
                'message' => 'درخواست پرداخت در حال آماده‌سازی است. لطفاً صبر کنید؛ از ایجاد پرداخت‌های هم‌زمان جلوگیری می‌شود.',
            ],

            PaymentStatus::Pending => [
                'type' => 'info',
                'message' => 'یک پرداخت فعال برای این سفارش وجود دارد. با انتخاب ادامه پرداخت، همان درخواست پرداخت ادامه خواهد یافت.',
            ],

            PaymentStatus::Paid => null,
        };
    }

    private function shouldPoll(
        Order $order,
        ?Server $server,
    ): bool {
        if (
            $order->status === OrderStatus::Paid
            || $order->status
            === OrderStatus::Provisioning
        ) {
            return true;
        }

        return $order->status
            === OrderStatus::Fulfilled
            && $server instanceof Server
            && ! $server->isActive();
    }

    private function periodLabel(
        string $period,
    ): string {
        $label = config(
            "money.periods.{$period}.label",
        );

        return is_string($label)
            ? $label
            : $period;
    }

    /**
     * @return array{
     *     serverReady: bool,
     *     regionLabel: string,
     *     paid: bool,
     *     statusTone: string,
     *     amountLabel: string,
     *     amountToman: string,
     *     steps: list<array{
     *         title: string,
     *         description: string,
     *         icon: string,
     *         state: string
     *     }>,
     *     isProvisioning: bool,
     *     isFailed: bool,
     *     canCreateNewOrder: bool,
     *     estimatedReadyAt: int|null
     * }
     */
    private function presentationData(
        Order $order,
        ?Server $server,
    ): array {
        $serverReady =
            $server instanceof Server
            && $server->isActive();

        $paid = $this->isPaid(
            $order,
        );

        $providerDone =
            $order->status
            === OrderStatus::Fulfilled;

        return [
            'serverReady' => $serverReady,

            'regionLabel' => CloudRegionLabel::for(
                $order->region_id,
            ),

            'paid' => $paid,

            'statusTone' => $this->statusTone(
                order: $order,
                serverReady: $serverReady,
            ),

            'amountLabel' => $this->amountLabel(
                order: $order,
                paid: $paid,
            ),

            'amountToman' => MoneyFormatter::tomanFromRial(
                $order->final_amount,
            ),

            'steps' => $this->provisioningSteps(
                order: $order,
                serverReady: $serverReady,
                paid: $paid,
                providerDone: $providerDone,
            ),

            'isProvisioning' => $order->status
                === OrderStatus::Provisioning,

            'isFailed' => $order->status
                === OrderStatus::Failed,

            'canCreateNewOrder' => in_array(
                $order->status,
                [
                    OrderStatus::Expired,
                    OrderStatus::Cancelled,
                ],
                true,
            ),
            'estimatedReadyAt' => $order->paid_at
                ?->addSeconds(100)
                ->timestamp,
        ];
    }

    private function isPaid(
        Order $order,
    ): bool {
        if ($order->paid_at !== null) {
            return true;
        }

        return in_array(
            $order->status,
            [
                OrderStatus::Paid,
                OrderStatus::Provisioning,
                OrderStatus::Fulfilled,
                OrderStatus::Failed,
            ],
            true,
        );
    }

    private function statusTone(
        Order $order,
        bool $serverReady,
    ): string {
        return match ($order->status) {
            OrderStatus::PendingPayment,
            OrderStatus::Expired => 'warning',

            OrderStatus::Paid => 'success',

            OrderStatus::Provisioning => 'primary',

            OrderStatus::Fulfilled => $serverReady
                ? 'success'
                : 'info',

            OrderStatus::Failed => 'error',

            OrderStatus::Cancelled => 'neutral',
        };
    }

    private function amountLabel(
        Order $order,
        bool $paid,
    ): string {
        if ($paid) {
            return 'مبلغ پرداخت‌شده';
        }

        if (
            $order->status
            === OrderStatus::PendingPayment
        ) {
            return 'مبلغ قابل پرداخت';
        }

        return 'مبلغ سفارش';
    }

    /**
     * @return list<array{
     *     title: string,
     *     description: string,
     *     icon: string,
     *     state: string
     * }>
     */
    private function provisioningSteps(
        Order $order,
        bool $serverReady,
        bool $paid,
        bool $providerDone,
    ): array {
        $queued = in_array(
            $order->status,
            [
                OrderStatus::Provisioning,
                OrderStatus::Fulfilled,
                OrderStatus::Failed,
            ],
            true,
        );

        return [
            [
                'title' => 'پرداخت',
                'description' => 'تأیید تراکنش مالی',
                'icon' => 'lucide.credit-card',

                'state' => $paid
                    ? 'completed'
                    : (
                        $order->status
                        === OrderStatus::PendingPayment
                            ? 'current'
                            : 'upcoming'
                    ),
            ],

            [
                'title' => 'ثبت سفارش',
                'description' => 'ورود به صف آماده‌سازی',
                'icon' => 'lucide.list-checks',

                'state' => $queued
                    ? 'completed'
                    : (
                        $order->status
                        === OrderStatus::Paid
                            ? 'current'
                            : 'upcoming'
                    ),
            ],

            [
                'title' => 'ساخت VPS',
                'description' => 'ایجاد در زیرساخت ابری',
                'icon' => 'lucide.cloud-cog',

                'state' => match (true) {
                    $order->status
                    === OrderStatus::Failed => 'failed',

                    $providerDone => 'completed',

                    $order->status
                    === OrderStatus::Provisioning => 'current',

                    default => 'upcoming',
                },
            ],

            [
                'title' => 'اتصال',
                'description' => 'بررسی آمادگی SSH',
                'icon' => 'lucide.server',

                'state' => match (true) {
                    $serverReady => 'completed',

                    $providerDone => 'current',

                    default => 'upcoming',
                },
            ],
        ];
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
