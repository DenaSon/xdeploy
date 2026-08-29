<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PostHogQueryClient
{
    /** @param array<string, mixed> $query */
    public function query(array $query): array
    {
        $apiHost = rtrim(
            trim((string) config('services.posthog.api_host', '')),
            '/',
        );
        $projectId = trim(
            (string) config('services.posthog.project_id', ''),
        );
        $personalApiKey = trim(
            (string) config('services.posthog.personal_api_key', ''),
        );

        if (
            $apiHost === ''
            || ! str_starts_with($apiHost, 'https://')
            || $projectId === ''
            || ! ctype_digit($projectId)
            || $personalApiKey === ''
        ) {
            throw new RuntimeException(
                'PostHog reporting is not configured.',
            );
        }

        $response = $this->request($personalApiKey)
            ->post(
                $apiHost.'/api/projects/'.$projectId.'/query/',
                ['query' => $query],
            )
            ->throw();

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException(
                'PostHog returned an invalid query response.',
            );
        }

        return $payload;
    }

    private function request(string $personalApiKey): PendingRequest
    {
        return Http::withToken($personalApiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(
                max(
                    1,
                    (int) config(
                        'services.posthog.query_connect_timeout',
                        2,
                    ),
                ),
            )
            ->timeout(
                max(
                    2,
                    (int) config(
                        'services.posthog.query_timeout',
                        8,
                    ),
                ),
            );
    }
}
