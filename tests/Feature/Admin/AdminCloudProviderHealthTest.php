<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class AdminCloudProviderHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::flush();
    }

    public function test_admin_can_view_separated_health_and_purchase_readiness(): void
    {
        $health = $this->app->make(CloudProviderHealthEngine::class);

        $health->recordSuccess(
            provider: CloudProviderType::Arvan,
            latencyMs: 125.45,
            operation: 'http.get',
        );

        $health->recordFailure(
            provider: CloudProviderType::Liara,
            category: CloudProviderHealthFailureCategory::RateLimit,
            httpStatus: 429,
            latencyMs: 480.25,
            operation: 'http.get',
        );

        $this->actingAs($this->admin())
            ->get(route('admin.cloud-providers.index'))
            ->assertOk()
            ->assertSee('وضعیت ارائه‌دهندگان ابری')
            ->assertSee('سلامت سرویس')
            ->assertSee('آمادگی خرید')
            ->assertSee('ArvanCloud')
            ->assertSee('Liara')
            ->assertSee('سالم')
            ->assertSee('ناپایدار')
            ->assertSee('آماده خرید')
            ->assertSee('125.45 ms')
            ->assertSee('محدودیت نرخ درخواست')
            ->assertSee('HTTP 429')
            ->assertSee('http.get');
    }

    public function test_admin_sees_unknown_health_without_assuming_provider_is_healthy(): void
    {
        config()->set('cloud.providers.arvan.enabled', true);
        config()->set('cloud.providers.arvan.purchase_enabled', true);
        config()->set('cloud.providers.liara.enabled', false);
        config()->set('cloud.providers.liara.purchase_enabled', false);

        $this->actingAs($this->admin())
            ->get(route('admin.cloud-providers.index'))
            ->assertOk()
            ->assertSee('بدون داده')
            ->assertSee('عملیاتی:')
            ->assertSee('خرید در تنظیمات:')
            ->assertSee('فعال')
            ->assertSee('غیرفعال')
            ->assertSee('هنوز سیگنال Health معتبری برای این Provider ثبت نشده است.');
    }

    public function test_admin_can_distinguish_healthy_provider_from_blocked_purchase_readiness(): void
    {
        $health = $this->app->make(CloudProviderHealthEngine::class);

        $health->recordSuccess(CloudProviderType::Arvan);
        $health->recordFailure(
            provider: CloudProviderType::Arvan,
            category: CloudProviderHealthFailureCategory::Authentication,
            httpStatus: 401,
        );

        $this->actingAs($this->admin())
            ->get(route('admin.cloud-providers.index'))
            ->assertOk()
            ->assertSee('Health: سالم')
            ->assertSee('خرید مسدود')
            ->assertSee('دسترسی Provider نیاز به بررسی دارد')
            ->assertSee('احراز هویت')
            ->assertSee('HTTP 401');
    }

    public function test_non_admin_cannot_open_provider_health_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.cloud-providers.index'))
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
        ]);
    }
}
