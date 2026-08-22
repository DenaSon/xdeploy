<?php

declare(strict_types=1);

namespace App\Livewire\Admin\CloudProviders;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
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

        $providers = array_map(
            fn (CloudProviderType $provider): array => $this->providerViewData(
                provider: $provider,
                snapshot: $health->snapshot($provider),
            ),
            CloudProviderType::cases(),
        );

        return view(
            'livewire.admin.cloud-providers.index',
            [
                'providers' => $providers,
                'summary' => $this->summary($providers),
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
     *     snapshot: CloudProviderHealthSnapshot|null,
     *     error_label: string|null
     * }
     */
    private function providerViewData(
        CloudProviderType $provider,
        ?CloudProviderHealthSnapshot $snapshot,
    ): array {
        $status = $snapshot?->status;

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
    private function summary(array $providers): array
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
            CloudProviderHealthStatus::Degraded => 'اختلال نسبی',
            CloudProviderHealthStatus::Unavailable => 'در دسترس نیست',
            null => 'نامشخص',
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
