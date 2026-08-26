<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Domain\Billing\Enums\CustomerOrderState;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Cloud\Enums\CloudProviderType;

final class CustomerOrderPresentation
{
    /**
     * @return array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     icon: string
     * }
     */
    public static function state(
        CustomerOrderState $state,
        bool $paid,
    ): array {
        if (
            $state === CustomerOrderState::NeedsAttention
            && $paid
        ) {
            return [
                'label' => 'پرداخت انجام شده — نیازمند بررسی',
                'description' => 'پرداخت شما ثبت شده است، اما آماده‌سازی سرویس متوقف شده. پرداخت مجدد انجام ندهید.',
                'tone' => 'warning',
                'icon' => 'lucide.triangle-alert',
            ];
        }

        return match ($state) {
            CustomerOrderState::AwaitingPayment => [
                'label' => 'در انتظار پرداخت',
                'description' => 'برای ادامه سفارش، پرداخت را تکمیل کنید.',
                'tone' => 'warning',
                'icon' => 'lucide.credit-card',
            ],

            CustomerOrderState::PaymentPending => [
                'label' => 'در حال بررسی پرداخت',
                'description' => 'وضعیت پرداخت در حال بررسی است.',
                'tone' => 'info',
                'icon' => 'lucide.clock-3',
            ],

            CustomerOrderState::Processing => [
                'label' => 'در حال آماده‌سازی',
                'description' => 'پرداخت ثبت شده و سرویس در حال آماده‌سازی است.',
                'tone' => 'primary',
                'icon' => 'lucide.loader-circle',
            ],

            CustomerOrderState::Completed => [
                'label' => 'تکمیل شده',
                'description' => 'سفارش با موفقیت تکمیل شده است.',
                'tone' => 'success',
                'icon' => 'lucide.circle-check',
            ],

            CustomerOrderState::NeedsAttention => [
                'label' => 'نیازمند بررسی',
                'description' => 'فرایند سفارش متوقف شده و نیاز به بررسی دارد.',
                'tone' => 'error',
                'icon' => 'lucide.triangle-alert',
            ],

            CustomerOrderState::Cancelled => [
                'label' => 'لغو شده',
                'description' => 'این سفارش لغو شده است.',
                'tone' => 'neutral',
                'icon' => 'lucide.circle-x',
            ],

            CustomerOrderState::Expired => [
                'label' => 'منقضی شده',
                'description' => 'اعتبار قیمت این سفارش به پایان رسیده است.',
                'tone' => 'warning',
                'icon' => 'lucide.clock-alert',
            ],
        };
    }

    /**
     * @return array{label: string, tone: string}
     */
    public static function payment(
        ?PaymentStatus $status,
    ): array {
        if ($status === null) {
            return [
                'label' => 'بدون پرداخت',
                'tone' => 'neutral',
            ];
        }

        return match ($status) {
            PaymentStatus::Initiating => [
                'label' => 'در حال شروع',
                'tone' => 'info',
            ],
            PaymentStatus::Pending => [
                'label' => 'در انتظار تأیید',
                'tone' => 'info',
            ],
            PaymentStatus::Paid => [
                'label' => 'موفق',
                'tone' => 'success',
            ],
            PaymentStatus::Failed => [
                'label' => 'ناموفق',
                'tone' => 'error',
            ],
            PaymentStatus::Cancelled => [
                'label' => 'لغو شده',
                'tone' => 'neutral',
            ],
        };
    }

    public static function type(OrderType $type): string
    {
        return match ($type) {
            OrderType::Provisioning => 'خرید VPS',
            OrderType::Renewal => 'تمدید VPS',
        };
    }

    public static function provider(
        CloudProviderType $provider,
    ): string {
        return match ($provider) {
            CloudProviderType::Arvan => 'ابر آروان',
            CloudProviderType::Liara => 'لیارا',
        };
    }
}
