<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Analytics\ProductAnalyticsEvent;
use App\Infrastructure\Analytics\Jobs\SendPostHogEventJob;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PostHogProductAnalytics implements ProductAnalytics
{
    public function __construct(
        private AnalyticsPropertySanitizer $sanitizer,
    ) {}

    public function capture(
        ProductAnalyticsEvent $event,
        int|string $userId,
        array $properties = [],
    ): void {
        if (! (bool) config('services.posthog.enabled', false)) {
            return;
        }

        if (trim((string) config('services.posthog.api_key', '')) === '') {
            return;
        }

        $distinctId = $this->distinctId($userId);

        if ($distinctId === null) {
            return;
        }

        try {
            SendPostHogEventJob::dispatch(
                event: $event->value,
                distinctId: $distinctId,
                properties: $this->sanitizer->sanitize(
                    $properties,
                ),
            )
                ->onQueue(
                    (string) config(
                        'services.posthog.queue',
                        'default',
                    ),
                )
                ->afterCommit();
        } catch (Throwable $exception) {
            Log::warning(
                'analytics.posthog.dispatch_failed',
                [
                    'event' => $event->value,
                    'exception_type' => $exception::class,
                ],
            );
        }
    }

    private function distinctId(int|string $userId): ?string
    {
        $normalized = trim((string) $userId);

        if ($normalized === '') {
            return null;
        }

        return 'user:'.$normalized;
    }
}
