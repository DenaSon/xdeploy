<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/dev', function () {
    abort_unless(app()->isLocal(), 404);

    $apiKey = trim(
        (string) config('services.arvan_cloud.api_key')
    );

    $region = trim(
        (string) config('services.arvan_cloud.region')
    );

    $baseUrl = rtrim(
        (string) config('services.arvan_cloud.base_url'),
        '/'
    );

    abort_if(
        $apiKey === '',
        500,
        'Arvan Cloud API key is not configured.'
    );

    abort_if(
        $region === '',
        500,
        'Arvan Cloud region is not configured.'
    );

    $token = preg_replace(
        '/^(apikey|bearer)\s+/i',
        '',
        $apiKey
    );

    $endpoints = [
        'quota' => "regions/{$region}/quota",
        'networks' => "regions/{$region}/networks",
        'security_groups' => "regions/{$region}/securities",
        'ssh_keys' => "regions/{$region}/ssh-keys",
    ];

    try {
        $results = [];

        foreach ($endpoints as $name => $endpoint) {
            $startedAt = microtime(true);

            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => "Apikey {$token}",
                ])
                ->connectTimeout(10)
                ->timeout(30)
                ->get($endpoint);

            $results[$name] = [
                'endpoint' => "/{$endpoint}",
                'status' => $response->status(),
                'successful' => $response->successful(),

                'duration_ms' => round(
                    (microtime(true) - $startedAt) * 1000,
                    2
                ),

                'request_id' => $response->header('X-Request-ID'),

                'body' => $response->json()
                    ?? $response->body(),
            ];
        }

        return response()->json([
            'region' => [
                'code' => $region,
                'country' => 'Germany',
                'city' => 'Karlsruhe',
                'datacenter' => 'Goethe',
            ],

            'selected_image' => [
                'distribution' => 'ubuntu',
                'version' => '24.04',
                'id' => '00aaa9d1-3e0a-468c-aaf4-334513981e42',
            ],

            'selected_size' => [
                'id' => 'eco-2-2-0',
                'cpu' => 2,
                'memory_gb' => 2,
                'disk_gb' => 30,
            ],

            'resources' => $results,
        ]);
    } catch (ConnectionException $exception) {
        report($exception);

        return response()->json([
            'successful' => false,
            'message' => 'Could not connect to Arvan Cloud API.',
            'error' => $exception->getMessage(),
        ], 503);
    }
})->name('test.arvan-cloud.prerequisites');
