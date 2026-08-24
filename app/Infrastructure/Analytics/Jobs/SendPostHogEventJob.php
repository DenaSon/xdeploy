<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendPostHogEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 5;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public readonly string $event,
        public readonly string $distinctId,
        public readonly array $properties,
    ) {}

    public function handle(): void
    {
        if (! (bool) config('services.posthog.enabled', false)) {
            return;
        }

        $apiKey = trim((string) config('services.posthog.api_key', ''));

        if ($apiKey === '') {
            return;
        }

        $host = rtrim(
            (string) config(
                'services.posthog.host',
                'https://us.i.posthog.com',
            ),
            '/',
        );

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout(
                    max(
                        1,
                        (int) config(
                            'services.posthog.connect_timeout',
                            1,
                        ),
                    ),
                )
                ->timeout(
                    max(
                        1,
                        (int) config(
                            'services.posthog.timeout',
                            3,
                        ),
                    ),
                )
                ->post(
                    $host.'/i/v0/e/',
                    [
                        'api_key' => $apiKey,
                        'distinct_id' => $this->distinctId,
                        'event' => $this->event,
                        'properties' => [
                            ...$this->properties,
                            'event_source' => 'backend',
                            'analytics_schema_version' => 1,
                        ],
                    ],
                );

            if (! $response->successful()) {
                Log::warning(
                    'analytics.posthog.capture_failed',
                    [
                        'event' => $this->event,
                        'status' => $response->status(),
                    ],
                );
            }
        } catch (Throwable $exception) {
            Log::warning(
                'analytics.posthog.capture_failed',
                [
                    'event' => $this->event,
                    'exception_type' => $exception::class,
                ],
            );
        }
    }
}
