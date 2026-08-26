<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Infrastructure\Analytics\Jobs\SendPostHogEventJob;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SendPostHogEventJobTest extends TestCase
{
    public function test_it_sends_the_expected_capture_payload(): void
    {
        config()->set('services.posthog.enabled', true);
        config()->set('services.posthog.api_key', 'phc_test');
        config()->set(
            'services.posthog.host',
            'https://us.i.posthog.com',
        );

        Http::fake([
            'https://us.i.posthog.com/i/v0/e/' =>
                Http::response([], 200),
        ]);

        $job = new SendPostHogEventJob(
            event: 'server_ready',
            distinctId: 'user:17',
            properties: [
                'order_id' => 91,
                'server_id' => 11,
            ],
        );

        $job->handle();

        Http::assertSent(
            static fn (Request $request): bool =>
                $request->url()
                    === 'https://us.i.posthog.com/i/v0/e/'
                && $request['api_key'] === 'phc_test'
                && $request['distinct_id'] === 'user:17'
                && $request['event'] === 'server_ready'
                && $request['properties']['order_id'] === 91
                && $request['properties']['server_id'] === 11
                && $request['properties']['event_source'] === 'backend'
                && $request['properties']['analytics_schema_version'] === 1,
        );
    }

    public function test_transport_failure_does_not_escape_the_job(): void
    {
        config()->set('services.posthog.enabled', true);
        config()->set('services.posthog.api_key', 'phc_test');
        config()->set(
            'services.posthog.host',
            'https://us.i.posthog.com',
        );

        Http::fake(static function (): void {
            throw new \RuntimeException(
                'Synthetic transport failure.',
            );
        });

        $job = new SendPostHogEventJob(
            event: 'order_created',
            distinctId: 'user:17',
            properties: ['order_id' => 91],
        );

        $job->handle();

        self::assertTrue(true);
    }
}
