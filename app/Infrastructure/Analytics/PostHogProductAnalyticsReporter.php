<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Application\Analytics\Contracts\ProductAnalyticsReporting;
use App\Application\Analytics\ProductAnalyticsReport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PostHogProductAnalyticsReporter implements ProductAnalyticsReporting
{
    private const ALLOWED_RANGES = [7, 30, 90];

    public function __construct(
        private PostHogQueryClient $client,
    ) {}

    public function report(int $days): ProductAnalyticsReport
    {
        $days = in_array($days, self::ALLOWED_RANGES, true)
            ? $days
            : 7;

        if (! $this->client->isConfigured()) {
            return ProductAnalyticsReport::unavailable(
                $days,
                'not_configured',
            );
        }

        $ttl = max(
            30,
            (int) config(
                'services.posthog.report_cache_seconds',
                120,
            ),
        );

        try {
            return Cache::remember(
                'analytics:product-report:v1:'.$days,
                $ttl,
                fn (): ProductAnalyticsReport => $this->buildReport($days),
            );
        } catch (Throwable $exception) {
            Log::warning(
                'analytics.posthog.reporting_failed',
                ['exception_type' => $exception::class],
            );

            return ProductAnalyticsReport::unavailable(
                $days,
                'query_failed',
            );
        }
    }

    private function buildReport(int $days): ProductAnalyticsReport
    {
        $purchase = $this->funnel(
            $days,
            [
                'landing_viewed',
                'authentication_completed',
                'buy_viewed',
                'order_created',
                'payment_started',
                'payment_succeeded',
            ],
        );
        $fulfillment = $this->funnel(
            $days,
            [
                'payment_succeeded',
                'provisioning_started',
                'server_fulfilled',
                'server_ready',
            ],
        );
        $activation = $this->funnel(
            $days,
            [
                'server_ready',
                'application_install_started',
                'application_running',
            ],
        );

        $overview = $this->overview(
            $purchase,
            $fulfillment,
            $activation,
        );

        return new ProductAnalyticsReport(
            available: true,
            days: $days,
            overview: $overview,
            funnels: [
                'purchase' => $purchase,
                'fulfillment' => $fulfillment,
                'activation' => $activation,
            ],
            acquisition: $this->optionalBreakdown(
                $days,
                event: 'landing_viewed',
                property: 'first_touch_source',
                math: 'dau',
                labels: [],
                nullLabel: 'مستقیم / بدون UTM',
            ),
            payments: $this->optionalTrendTotals(
                $days,
                [
                    'payment_started' => 'شروع‌شده',
                    'payment_succeeded' => 'موفق',
                    'payment_failed' => 'ناموفق',
                    'payment_cancelled' => 'لغوشده',
                ],
            ),
            providers: $this->optionalBreakdown(
                $days,
                event: 'payment_succeeded',
                property: 'provider',
                math: 'total',
                labels: [
                    'arvan' => 'ArvanCloud',
                    'liara' => 'Liara',
                    'parspack' => 'ParsPack',
                ],
                nullLabel: 'نامشخص',
            ),
            applications: $this->optionalBreakdown(
                $days,
                event: 'application_running',
                property: 'application_type',
                math: 'total',
                labels: [
                    'marzban' => 'Marzban',
                    'wordpress' => 'WordPress',
                    'n8n' => 'n8n',
                ],
                nullLabel: 'نامشخص',
            ),
        );
    }

    /**
     * @param  list<string>  $events
     * @return list<array<string, int|float|string|null>>
     */
    private function funnel(int $days, array $events): array
    {
        $payload = $this->client->query([
            'kind' => 'FunnelsQuery',
            'dateRange' => ['date_from' => '-'.$days.'d'],
            'filterTestAccounts' => true,
            'properties' => [],
            'funnelsFilter' => [
                'funnelOrderType' => 'ordered',
                'funnelVizType' => 'steps',
                'funnelStepReference' => 'total',
                'funnelWindowInterval' => 14,
                'funnelWindowIntervalUnit' => 'day',
            ],
            'series' => array_map(
                static fn (string $event): array => [
                    'kind' => 'EventsNode',
                    'event' => $event,
                    'optionalInFunnel' => false,
                ],
                $events,
            ),
        ]);

        $results = $payload['results'] ?? null;

        if (! is_array($results)) {
            throw new \RuntimeException(
                'PostHog funnel response has no results.',
            );
        }

        $rows = [];
        $firstCount = null;
        $previousCount = null;

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $event = is_string($result['name'] ?? null)
                ? $result['name']
                : null;

            if ($event === null) {
                continue;
            }

            $count = max(0, (int) ($result['count'] ?? 0));
            $firstCount ??= $count;

            $rows[] = [
                'event' => $event,
                'label' => $this->eventLabel($event),
                'count' => $count,
                'from_start_percent' => $this->percentage(
                    $count,
                    $firstCount,
                ),
                'from_previous_percent' => $previousCount === null
                    ? 100.0
                    : $this->percentage($count, $previousCount),
                'median_seconds' => is_numeric(
                    $result['median_conversion_time'] ?? null,
                )
                    ? round(
                        (float) $result['median_conversion_time'],
                        1,
                    )
                    : null,
            ];

            $previousCount = $count;
        }

        return $rows;
    }

    /**
     * @param  list<array<string, int|float|string|null>>  $purchase
     * @param  list<array<string, int|float|string|null>>  $fulfillment
     * @param  list<array<string, int|float|string|null>>  $activation
     * @return array<string, int|float>
     */
    private function overview(
        array $purchase,
        array $fulfillment,
        array $activation,
    ): array {
        $visitors = $this->funnelCount($purchase, 'landing_viewed');
        $authenticated = $this->funnelCount(
            $purchase,
            'authentication_completed',
        );
        $buyViewed = $this->funnelCount($purchase, 'buy_viewed');
        $orders = $this->funnelCount($purchase, 'order_created');
        $paymentStarted = $this->funnelCount(
            $purchase,
            'payment_started',
        );
        $paymentSucceeded = $this->funnelCount(
            $purchase,
            'payment_succeeded',
        );
        $readyBase = $this->funnelCount(
            $fulfillment,
            'payment_succeeded',
        );
        $serverReady = $this->funnelCount(
            $fulfillment,
            'server_ready',
        );
        $activationBase = $this->funnelCount(
            $activation,
            'server_ready',
        );
        $applicationRunning = $this->funnelCount(
            $activation,
            'application_running',
        );

        return [
            'visitors' => $visitors,
            'authenticated' => $authenticated,
            'orders' => $orders,
            'payments' => $paymentSucceeded,
            'server_ready' => $serverReady,
            'activated' => $applicationRunning,
            'auth_conversion' => $this->percentage(
                $authenticated,
                $visitors,
            ),
            'order_conversion' => $this->percentage(
                $orders,
                $buyViewed,
            ),
            'payment_conversion' => $this->percentage(
                $paymentSucceeded,
                $paymentStarted,
            ),
            'server_ready_rate' => $this->percentage(
                $serverReady,
                $readyBase,
            ),
            'activation_rate' => $this->percentage(
                $applicationRunning,
                $activationBase,
            ),
        ];
    }

    /**
     * @param  array<string, string>  $events
     * @return list<array{label: string, value: int}>
     */
    private function optionalTrendTotals(
        int $days,
        array $events,
    ): array {
        try {
            $payload = $this->client->query([
                'kind' => 'TrendsQuery',
                'dateRange' => ['date_from' => '-'.$days.'d'],
                'filterTestAccounts' => true,
                'interval' => 'day',
                'properties' => [],
                'series' => array_map(
                    static fn (string $event): array => [
                        'kind' => 'EventsNode',
                        'event' => $event,
                        'math' => 'total',
                    ],
                    array_keys($events),
                ),
                'trendsFilter' => [
                    'display' => 'ActionsBarValue',
                    'aggregationAxisFormat' => 'numeric',
                ],
            ]);

            $rows = [];

            foreach (($payload['results'] ?? []) as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $event = $result['action']['id'] ?? $result['label'] ?? null;

                if (! is_string($event) || ! isset($events[$event])) {
                    continue;
                }

                $rows[] = [
                    'label' => $events[$event],
                    'value' => max(
                        0,
                        (int) round(
                            (float) ($result['aggregated_value'] ?? 0),
                        ),
                    ),
                ];
            }

            return $rows;
        } catch (Throwable $exception) {
            $this->logOptionalFailure('payment_outcomes', $exception);

            return [];
        }
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{label: string, value: int}>
     */
    private function optionalBreakdown(
        int $days,
        string $event,
        string $property,
        string $math,
        array $labels,
        string $nullLabel,
    ): array {
        try {
            $payload = $this->client->query([
                'kind' => 'TrendsQuery',
                'dateRange' => ['date_from' => '-'.$days.'d'],
                'filterTestAccounts' => true,
                'interval' => 'day',
                'properties' => [],
                'series' => [[
                    'kind' => 'EventsNode',
                    'event' => $event,
                    'math' => $math,
                ]],
                'breakdownFilter' => [
                    'breakdown_limit' => 10,
                    'breakdowns' => [[
                        'property' => $property,
                        'type' => 'event',
                    ]],
                ],
                'trendsFilter' => [
                    'display' => 'ActionsTable',
                    'aggregationAxisFormat' => 'numeric',
                ],
            ]);

            $rows = [];

            foreach (($payload['results'] ?? []) as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $value = $result['breakdown_value'][0]
                    ?? $result['label']
                    ?? null;

                if (! is_string($value)) {
                    continue;
                }

                $normalized = trim($value);
                $label = $normalized === '$$_posthog_breakdown_null_$$'
                    || $normalized === ''
                        ? $nullLabel
                        : ($labels[$normalized] ?? $normalized);

                $rows[] = [
                    'label' => $label,
                    'value' => max(
                        0,
                        (int) round(
                            (float) ($result['aggregated_value'] ?? 0),
                        ),
                    ),
                ];
            }

            usort(
                $rows,
                static fn (array $left, array $right): int =>
                    $right['value'] <=> $left['value'],
            );

            return $rows;
        } catch (Throwable $exception) {
            $this->logOptionalFailure(
                $event.'.'.$property,
                $exception,
            );

            return [];
        }
    }

    /**
     * @param  list<array<string, int|float|string|null>>  $rows
     */
    private function funnelCount(array $rows, string $event): int
    {
        foreach ($rows as $row) {
            if (($row['event'] ?? null) === $event) {
                return (int) ($row['count'] ?? 0);
            }
        }

        return 0;
    }

    private function percentage(int $value, int $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return round(($value / $base) * 100, 1);
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'landing_viewed' => 'بازدید صفحه اصلی',
            'authentication_completed' => 'احراز هویت',
            'buy_viewed' => 'مشاهده خرید VPS',
            'order_created' => 'ایجاد سفارش',
            'payment_started' => 'شروع پرداخت',
            'payment_succeeded' => 'پرداخت موفق',
            'provisioning_started' => 'شروع ساخت سرور',
            'server_fulfilled' => 'تحویل از Provider',
            'server_ready' => 'سرور آماده',
            'application_install_started' => 'شروع نصب برنامه',
            'application_running' => 'برنامه در حال اجرا',
            default => $event,
        };
    }

    private function logOptionalFailure(
        string $section,
        Throwable $exception,
    ): void {
        Log::notice(
            'analytics.posthog.reporting_section_failed',
            [
                'section' => $section,
                'exception_type' => $exception::class,
            ],
        );
    }
}
