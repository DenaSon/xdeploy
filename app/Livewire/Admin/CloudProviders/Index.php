<?php

declare(strict_types=1);

namespace App\Livewire\Admin\CloudProviders;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Application\Cloud\Services\CloudProviderPurchaseReadinessService;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderPurchaseReadinessStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('وضعیت ارائه‌دهندگان ابری')]
final class Index extends Component
{
    public function render(): View
    {
        /** @var CloudProviderHealthEngine $health */
        $health = app(CloudProviderHealthEngine::class);
        /** @var CloudProviderPurchaseReadinessService $readiness */
        $readiness = app(CloudProviderPurchaseReadinessService::class);

        $providers = array_map(
            fn (CloudProviderType $provider): array => $this->providerViewData(
                provider: $provider,
                snapshot: $health->snapshot($provider),
                readiness: $readiness,
            ),
            CloudProviderType::cases(),
        );

        return view(
            'livewire.admin.cloud-providers.index',
            [
                'providers' => $providers,
                'healthSummary' => $this->healthSummary($providers),
                'purchaseSummary' => $this->purchaseSummary($providers),
                'probeEnabled' => (bool) config(
                    'cloud_health.probe.enabled',
                    true,
                ),
                'stateTtlSeconds' => (int) config(
                    'cloud_health.state_ttl_seconds',
                    1_800,
                ),
            ],
        );
    }

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     enabled: bool,
     *     purchase_enabled: bool,
     *     status: string,
     *     status_label: string,
     *     status_class: string,
     *     purchase_readiness: string,
     *     purchase_readiness_label: string,
     *     purchase_readiness_class: string,
     *     purchase_allowed: bool,
     *     purchase_readiness_reason: string|null,
     *     snapshot: CloudProviderHealthSnapshot|null,
     *     error_label: string|null
     * }
     */
    private function providerViewData(
        CloudProviderType $provider,
        ?CloudProviderHealthSnapshot $snapshot,
        CloudProviderPurchaseReadinessService $readiness,
    ): array {
        $status = $snapshot?->status;
        $purchaseReadiness = $readiness->evaluate($provider);

        return [
            'key' => $provider->value,
            'name' => $this->providerName($provider),
            'enabled' => config(
                sprintf('cloud.providers.%s.enabled', $provider->value),
            ) === true,
            'purchase_enabled' => config(
                sprintf(
                    'cloud.providers.%s.purchase_enabled',
                    $provider->value,
                ),
            ) === true,
            'status' => $status?->value ?? 'unknown',
            'status_label' => $this->statusLabel($status),
            'status_class' => $this->statusClass($status),
            'purchase_readiness' => $purchaseReadiness->status->value,
            'purchase_readiness_label' => $this->purchaseReadinessLabel(
                $purchaseReadiness->status,
            ),
            'purchase_readiness_class' => $purchaseReadiness->allowsPurchase()
                ? 'badge-success'
                : 'badge-warning',
            'purchase_allowed' => $purchaseReadiness->allowsPurchase(),
            'purchase_readiness_reason' => $purchaseReadiness->allowsPurchase()
                ? null
                : $this->purchaseReadinessReason($purchaseReadiness->status),
            'snapshot' => $snapshot,
            'error_label' => $snapshot?->lastErrorCategory !== null
                ? $this->errorLabel($snapshot->lastErrorCategory)
                : null,
        ];
    }

    /**
     * @param  list<array{status: string}>  $providers
     * @return array{healthy: int, degraded: int, unavailable: int, unknown: int}
     */
    private function healthSummary(array $providers): array
    {
        $summary = [
            'healthy' => 0,
            'degraded' => 0,
            'unavailable' => 0,
            'unknown' => 0,
        ];

        foreach ($providers as $provider) {
            $status = $provider['status'];

            if (! array_key_exists($status, $summary)) {
                $status = 'unknown';
            }

            $summary[$status]++;
        }

        return $summary;
    }

    /**
     * @param  list<array{purchase_allowed: bool}>  $providers
     * @return array{ready: int, blocked: int}
     */
    private function purchaseSummary(array $providers): array
    {
        $summary = [
            'ready' => 0,
            'blocked' => 0,
        ];

        foreach ($providers as $provider) {
            $summary[$provider['purchase_allowed'] ? 'ready' : 'blocked']++;
        }

        return $summary;
    }

    private function providerName(CloudProviderType $provider): string
    {
        return match ($provider) {
            CloudProviderType::Arvan => 'ArvanCloud',
            CloudProviderType::Liara => 'Liara',
            default => ucfirst($provider->value),
        };
    }

    private function statusLabel(?CloudProviderHealthStatus $status): string
    {
        return match ($status) {
            CloudProviderHealthStatus::Healthy => 'سالم',
            CloudProviderHealthStatus::Degraded => 'ناپایدار',
            CloudProviderHealthStatus::Unavailable => 'خارج از دسترس',
            null => 'بدون داده',
        };
    }

    private function statusClass(?CloudProviderHealthStatus $status): string
    {
        return match ($status) {
            CloudProviderHealthStatus::Healthy => 'badge-success',
            CloudProviderHealthStatus::Degraded => 'badge-warning',
            CloudProviderHealthStatus::Unavailable => 'badge-error',
            null => 'badge-ghost',
        };
    }

    private function purchaseReadinessLabel(
        CloudProviderPurchaseReadinessStatus $status,
    ): string {
        return $status === CloudProviderPurchaseReadinessStatus::Ready
            ? 'آماده خرید'
            : 'خرید مسدود';
    }

    private function purchaseReadinessReason(
        CloudProviderPurchaseReadinessStatus $status,
    ): string {
        return match ($status) {
            CloudProviderPurchaseReadinessStatus::BlockedCredentials => 'دسترسی Provider نیاز به بررسی دارد',
            CloudProviderPurchaseReadinessStatus::BlockedConfiguration => 'پیکربندی خرید نیاز به بررسی دارد',
            CloudProviderPurchaseReadinessStatus::BlockedBalance => 'موجودی Provider برای خرید کافی نیست',
            CloudProviderPurchaseReadinessStatus::TemporarilyUnavailable => 'Provider موقتاً برای خرید پاسخ‌گو نیست',
            CloudProviderPurchaseReadinessStatus::Ready => 'آماده خرید',
        };
    }

    private function errorLabel(
        CloudProviderHealthFailureCategory $category,
    ): string {
        return match ($category) {
            CloudProviderHealthFailureCategory::Authentication => 'احراز هویت',
            CloudProviderHealthFailureCategory::Authorization => 'مجوز دسترسی',
            CloudProviderHealthFailureCategory::Configuration => 'پیکربندی',
            CloudProviderHealthFailureCategory::Connection => 'اتصال',
            CloudProviderHealthFailureCategory::InsufficientBalance => 'موجودی ناکافی',
            CloudProviderHealthFailureCategory::NotFound => 'منبع پیدا نشد',
            CloudProviderHealthFailureCategory::ProviderServerError => 'خطای سرور Provider',
            CloudProviderHealthFailureCategory::RateLimit => 'محدودیت نرخ درخواست',
            CloudProviderHealthFailureCategory::Timeout => 'Timeout',
            CloudProviderHealthFailureCategory::UnexpectedResponse => 'پاسخ غیرمنتظره',
            CloudProviderHealthFailureCategory::UnexpectedStatus => 'HTTP status غیرمنتظره',
            CloudProviderHealthFailureCategory::Validation => 'اعتبارسنجی',
            default => str_replace('_', ' ', $category->value),
        };
    }
}
