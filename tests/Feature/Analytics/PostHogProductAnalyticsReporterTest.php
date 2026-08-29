<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Infrastructure\Analytics\PostHogProductAnalyticsReporter;
use App\Infrastructure\Analytics\PostHogQueryClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PostHogProductAnalyticsReporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('services.posthog.api_host', 'https://us.posthog.com');
        config()->set('services.posthog.project_id', '556948');
        config()->set('services.posthog.personal_api_key', 'phx_test_read_only');
        config()->set('services.posthog.report_cache_seconds', 120);
    }

    public function test_it_builds_aggregate_report_from_posthog_queries(): void
    {
        Http::fake(function (Request $request) {
            $query = $request['query'];

            self::assertTrue($query['filterTestAccounts']);

            if ($query['kind'] === 'FunnelsQuery') {
                $events = array_column($query['series'], 'event');

                return Http::response([
                    'results' => $this->funnelResults($events),
                ]);
            }

            $breakdowns = $query['breakdownFilter']['breakdowns'] ?? [];
            $property = $breakdowns[0]['property'] ?? null;

            if ($property === 'first_touch_source') {
                return Http::response([
                    'results' => [
                        $this->breakdownResult('instagram', 8),
                        $this->breakdownResult(
                            '$$_posthog_breakdown_null_$$',
                            27,
                        ),
                    ],
                ]);
            }

            if ($property === 'provider') {
                return Http::response([
                    'results' => [
                        $this->breakdownResult('liara', 2),
                        $this->breakdownResult('parspack', 1),
                    ],
                ]);
            }

            if ($property === 'application_type') {
                return Http::response([
                    'results' => [
                        $this->breakdownResult('wordpress', 2),
                        $this->breakdownResult('n8n', 1),
                    ],
                ]);
            }

            return Http::response([
                'results' => [
                    $this->trendResult('payment_started', 4),
                    $this->trendResult('payment_succeeded', 3),
                    $this->trendResult('payment_failed', 1),
                    $this->trendResult('payment_cancelled', 0),
                ],
            ]);
        });

        $report = (new PostHogProductAnalyticsReporter(
            new PostHogQueryClient,
        ))->report(7);

        self::assertTrue($report->available);
        self::assertSame(35, $report->overview['visitors']);
        self::assertSame(2, $report->overview['payments']);
        self::assertSame(2, $report->overview['server_ready']);
        self::assertSame(1, $report->overview['activated']);
        self::assertSame(50.0, $report->overview['activation_rate']);
        self::assertSame('instagram', $report->acquisition[1]['label']);
        self::assertSame('Liara', $report->providers[0]['label']);
        self::assertSame('WordPress', $report->applications[0]['label']);

        Http::assertSent(
            static fn (Request $request): bool =>
                $request->url()
                    === 'https://us.posthog.com/api/projects/556948/query/'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer phx_test_read_only',
                ),
        );
    }

    public function test_missing_reporting_credentials_fail_closed_without_http_request(): void
    {
        Http::fake();
        config()->set('services.posthog.personal_api_key', null);

        $report = (new PostHogProductAnalyticsReporter(
            new PostHogQueryClient,
        ))->report(30);

        self::assertFalse($report->available);
        self::assertSame('not_configured', $report->unavailableReason);
        Http::assertNothingSent();
    }

    /**
     * @param  list<string>  $events
     * @return list<array<string, mixed>>
     */
    private function funnelResults(array $events): array
    {
        $counts = match ($events[0]) {
            'landing_viewed' => [35, 5, 4, 3, 3, 2],
            'payment_succeeded' => [2, 2, 2, 2],
            'server_ready' => [2, 2, 1],
            default => [],
        };

        return array_map(
            static fn (string $event, int $index): array => [
                'name' => $event,
                'count' => $counts[$index] ?? 0,
                'median_conversion_time' => $index === 0
                    ? null
                    : 10.0,
            ],
            $events,
            array_keys($events),
        );
    }

    /** @return array<string, mixed> */
    private function breakdownResult(string $label, int $value): array
    {
        return [
            'label' => $label,
            'breakdown_value' => [$label],
            'aggregated_value' => $value,
        ];
    }

    /** @return array<string, mixed> */
    private function trendResult(string $event, int $value): array
    {
        return [
            'label' => $event,
            'action' => ['id' => $event],
            'aggregated_value' => $value,
        ];
    }
}
