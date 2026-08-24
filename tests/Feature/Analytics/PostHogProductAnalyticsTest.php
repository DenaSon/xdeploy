<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Application\Analytics\ProductAnalyticsEvent;
use App\Infrastructure\Analytics\AnalyticsPropertySanitizer;
use App\Infrastructure\Analytics\Jobs\SendPostHogEventJob;
use App\Infrastructure\Analytics\PostHogProductAnalytics;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PostHogProductAnalyticsTest extends TestCase
{
    public function test_it_queues_a_sanitized_event_when_enabled(): void
    {
        Queue::fake();

        config()->set('services.posthog.enabled', true);
        config()->set('services.posthog.api_key', 'phc_test');
        config()->set('services.posthog.queue', 'default');

        $analytics = new PostHogProductAnalytics(
            new AnalyticsPropertySanitizer,
        );

        $analytics->capture(
            ProductAnalyticsEvent::PaymentSucceeded,
            17,
            [
                'order_id' => 91,
                'failure_code' => null,
                'phone' => '09120000000',
                'gateway_reference' => 'sensitive-reference',
            ],
        );

        Queue::assertPushed(
            SendPostHogEventJob::class,
            static fn (SendPostHogEventJob $job): bool =>
                $job->event === 'payment_succeeded'
                && $job->distinctId === 'user:17'
                && $job->queue === 'default'
                && $job->properties === [
                    'order_id' => 91,
                    'failure_code' => null,
                ],
        );
    }

    public function test_it_does_not_queue_events_when_disabled(): void
    {
        Queue::fake();

        config()->set('services.posthog.enabled', false);
        config()->set('services.posthog.api_key', 'phc_test');

        $analytics = new PostHogProductAnalytics(
            new AnalyticsPropertySanitizer,
        );

        $analytics->capture(
            ProductAnalyticsEvent::OrderCreated,
            17,
            ['order_id' => 91],
        );

        Queue::assertNothingPushed();
    }
}
