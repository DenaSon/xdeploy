<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Analytics;

use App\Application\Analytics\ProductAnalyticsEvent;
use App\Infrastructure\Analytics\AnalyticsContext;
use App\Infrastructure\Analytics\AnalyticsPropertySanitizer;
use App\Infrastructure\Analytics\Jobs\SendPostHogEventJob;
use App\Infrastructure\Analytics\PostHogProductAnalytics;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PostHogProductAnalyticsTest extends TestCase
{
    public function test_capture_snapshots_safe_request_context_before_queueing(): void
    {
        config([
            'services.posthog.enabled' => true,
            'services.posthog.api_key' => 'phc_test_key',
            'services.posthog.queue' => 'default',
            'services.posthog.internal_user_ids' => [],
        ]);

        Queue::fake();

        $user = new User;
        $user->forceFill([
            'id' => 51,
            'is_admin' => true,
        ]);

        $this->actingAs($user);

        Route::get(
            '/_analytics-capture/admin',
            static function (AnalyticsContext $context) {
                $analytics = new PostHogProductAnalytics(
                    new AnalyticsPropertySanitizer,
                    $context,
                );

                $analytics->capture(
                    ProductAnalyticsEvent::BuyViewed,
                    51,
                    [
                        'source' => 'buy_page',
                        'credential' => 'must-not-leave',
                    ],
                );

                return response()->noContent();
            },
        )->name('panel.servers.buy');

        $this->get('/_analytics-capture/admin')
            ->assertNoContent();

        Queue::assertPushed(
            SendPostHogEventJob::class,
            static function (SendPostHogEventJob $job): bool {
                return $job->event === 'buy_viewed'
                    && $job->distinctId === 'user:51'
                    && $job->properties['source'] === 'buy_page'
                    && $job->properties['route_name'] === 'panel.servers.buy'
                    && $job->properties['is_internal'] === true
                    && $job->properties['$set'] === [
                        'is_internal' => true,
                    ]
                    && ! array_key_exists(
                        'credential',
                        $job->properties,
                    );
            },
        );
    }

    protected function automaticallyVerifyAdminPasskey(): bool
    {
        return false;
    }
}
