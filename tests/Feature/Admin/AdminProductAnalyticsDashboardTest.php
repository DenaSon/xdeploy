<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Application\Analytics\Contracts\ProductAnalyticsReporting;
use App\Application\Analytics\ProductAnalyticsReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminProductAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_product_analytics(): void
    {
        $this->get(
            route('admin.analytics.dashboard'),
        )->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_open_product_analytics(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('admin.analytics.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_view_aggregate_product_analytics_without_secrets(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        config()->set(
            'services.posthog.personal_api_key',
            'phx_must_never_render',
        );

        $this->app->instance(
            ProductAnalyticsReporting::class,
            new class implements ProductAnalyticsReporting
            {
                public function report(int $days): ProductAnalyticsReport
                {
                    return new ProductAnalyticsReport(
                        available: true,
                        days: $days,
                        overview: [
                            'visitors' => 35,
                            'authenticated' => 5,
                            'orders' => 3,
                            'payments' => 2,
                            'server_ready' => 2,
                            'activated' => 1,
                            'auth_conversion' => 14.3,
                            'order_conversion' => 75.0,
                            'payment_conversion' => 66.7,
                            'server_ready_rate' => 100.0,
                            'activation_rate' => 50.0,
                        ],
                        funnels: [
                            'purchase' => [],
                            'fulfillment' => [],
                            'activation' => [],
                        ],
                        acquisition: [
                            ['label' => 'instagram', 'value' => 8],
                        ],
                        payments: [
                            ['label' => 'موفق', 'value' => 2],
                        ],
                        providers: [
                            ['label' => 'Liara', 'value' => 2],
                        ],
                        applications: [
                            ['label' => 'WordPress', 'value' => 1],
                        ],
                    );
                }
            },
        );

        $this
            ->actingAs($user)
            ->get(route('admin.analytics.dashboard'))
            ->assertOk()
            ->assertSee('تحلیل محصول')
            ->assertSee('Purchase Funnel')
            ->assertSee('منبع جذب · First Touch')
            ->assertSee('Liara')
            ->assertSee('WordPress')
            ->assertDontSee('phx_must_never_render');
    }

    public function test_admin_page_survives_missing_posthog_reporting_configuration(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        config()->set('services.posthog.personal_api_key', null);
        config()->set('services.posthog.project_id', null);

        $this
            ->actingAs($user)
            ->get(route('admin.analytics.dashboard'))
            ->assertOk()
            ->assertSee('گزارش تحلیلی در دسترس نیست')
            ->assertSee('پیکربندی نشده است');
    }
}
