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

    public function test_admin_can_view_provider_health_snapshots(): void
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
            ->assertSee('ArvanCloud')
            ->assertSee('Liara')
            ->assertSee('سالم')
            ->assertSee('اختلال نسبی')
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
            ->assertSee('نامشخص')
            ->assertSee('عملیاتی')
            ->assertSee('خرید فعال')
            ->assertSee('غیرفعال')
            ->assertSee('خرید غیرفعال')
            ->assertSee('هنوز Health signal معتبری برای این Provider ثبت نشده است.');
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
