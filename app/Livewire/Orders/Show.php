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
                'چند لحظه صبر کنید و دوباره تلاش کنید.',
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
                'سفارش حفظ شده است. چند لحظه دیگر دوباره تلاش کنید.',
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
                    'description' => 'سرور ساخته شده و اتصال xDeploy نیز آماده است.',
                    'icon' => 'lucide.circle-check',
                    'badge' => 'badge-success',
                ];
            }

            return [
                'label' => 'VPS ساخته شد',
                'description' => 'ساخت سرور در Cloud کامل شده و بررسی اتصال xDeploy در حال انجام است.',
                'icon' => 'lucide.server',
                'badge' => 'badge-info',
            ];
        }

        return match ($order->status) {
            OrderStatus::PendingPayment => [
                'label' => 'در انتظار پرداخت',
                'description' => 'سفارش ثبت شده و برای ادامه باید پرداخت انجام شود.',
                'icon' => 'lucide.credit-card',
                'badge' => 'badge-warning',
            ],

            OrderStatus::Paid => [
                'label' => 'پرداخت تأیید شد',
                'description' => 'پرداخت تأیید شده و سفارش در صف ساخت سرور قرار گرفته است.',
                'icon' => 'lucide.badge-check',
                'badge' => 'badge-success',
            ],

            OrderStatus::Provisioning => [
                'label' => 'در حال ساخت VPS',
                'description' => 'درخواست ساخت به Cloud Provider ارسال شده است.',
                'icon' => 'lucide.loader-circle',
                'badge' => 'badge-info',
            ],

            OrderStatus::Failed => [
                'label' => 'ساخت سرور ناموفق بود',
                'description' => 'ساخت خودکار متوقف شده است. برای جلوگیری از ایجاد سرور تکراری، تلاش مجدد خودکار انجام نمی‌شود.',
                'icon' => 'lucide.triangle-alert',
                'badge' => 'badge-error',
            ],

            OrderStatus::Cancelled => [
                'label' => 'سفارش لغو شد',
                'description' => 'این سفارش دیگر وارد فرایند ساخت سرور نمی‌شود.',
                'icon' => 'lucide.circle-x',
                'badge' => 'badge-neutral',
            ],

            OrderStatus::Expired => [
                'label' => 'پیش‌فاکتور منقضی شد',
                'description' => 'اعتبار قیمت این سفارش تمام شده است. یک سفارش جدید ایجاد کنید.',
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
                'message' => 'پرداخت قبلی لغو شد. اگر پیش‌فاکتور هنوز معتبر باشد می‌توانید دوباره پرداخت را شروع کنید.',
            ],

            PaymentStatus::Failed => [
                'type' => 'error',
                'message' => 'شروع پرداخت قبلی ناموفق بود. سفارش شما حفظ شده و می‌توانید دوباره تلاش کنید.',
            ],

            PaymentStatus::Initiating => [
                'type' => 'info',
                'message' => 'درخواست پرداخت در حال آماده‌سازی است. از ایجاد چند پرداخت هم‌زمان جلوگیری می‌شود.',
            ],

            PaymentStatus::Pending => [
                'type' => 'info',
                'message' => 'یک پرداخت فعال برای این سفارش وجود دارد. دکمه پرداخت شما را به همان درخواست برمی‌گرداند.',
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
