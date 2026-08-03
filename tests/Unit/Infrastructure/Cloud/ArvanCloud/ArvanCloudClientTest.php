<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_sends_an_authenticated_get_request(): void
    {
        Http::fake([
            'https://api.example.test/ecc/v1/*' => Http::response([
                'data' => [],
            ]),
        ]);

        $result = $this->client()->get(
            'regions/eu-west1-a/images',
            [
                'type' => 'distributions',
            ],
        );

        $this->assertSame([
            'data' => [],
        ], $result);

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'GET'
                    && $request->url() ===
                    'https://api.example.test/ecc/v1/regions/eu-west1-a/images?type=distributions'
                    && $request->hasHeader(
                        'Authorization',
                        'Apikey test-api-key',
                    )
                    && $request->hasHeader(
                        'Accept',
                        'application/json',
                    );
            },
        );
    }

    public function test_it_does_not_use_bearer_authentication(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [],
            ]),
        ]);

        $this->client()->get('regions');

        Http::assertSent(
            fn (Request $request): bool => ! $request->hasHeader(
                'Authorization',
                'Bearer test-api-key',
            ),
        );
    }

    public function test_it_decodes_a_valid_json_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    [
                        'id' => 'eu-west1-a',
                    ],
                ],
            ]),
        ]);

        $result = $this->client()->get('regions');

        $this->assertSame(
            'eu-west1-a',
            $result['data'][0]['id'],
        );
    }

    public function test_it_rejects_invalid_json(): void
    {
        Http::fake([
            '*' => Http::response(
                '{"invalid-json":',
                200,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        ]);

        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'Cloud provider returned invalid JSON.',
        );

        $this->client()->get('regions');
    }

    public function test_it_rejects_scalar_json(): void
    {
        Http::fake([
            '*' => Http::response(
                '"unexpected-string"',
                200,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        ]);

        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->client()->get('regions');
    }

    #[DataProvider('mappedStatusProvider')]
    public function test_it_maps_http_statuses_to_cloud_exceptions(
        int $status,
        string $expectedException,
    ): void {
        Http::fake([
            '*' => Http::response([
                'message' => 'Provider error',
            ], $status),
        ]);

        $this->expectException($expectedException);

        $this->client()->get('regions');
    }

    /**
     * @return array<string, array{int, class-string<\Throwable>}>
     */
    public static function mappedStatusProvider(): array
    {
        return [
            'bad request' => [
                400,
                CloudValidationException::class,
            ],
            'unauthorized' => [
                401,
                CloudAuthenticationException::class,
            ],
            'forbidden' => [
                403,
                CloudAuthorizationException::class,
            ],
            'not found' => [
                404,
                CloudResourceNotFoundException::class,
            ],
            'request timeout' => [
                408,
                CloudConnectionException::class,
            ],
            'conflict' => [
                409,
                CloudValidationException::class,
            ],
            'unprocessable content' => [
                422,
                CloudValidationException::class,
            ],
            'server error' => [
                500,
                CloudConnectionException::class,
            ],
            'service unavailable' => [
                503,
                CloudConnectionException::class,
            ],
            'redirect' => [
                302,
                CloudUnexpectedResponseException::class,
            ],
        ];
    }

    public function test_it_maps_rate_limit_and_retry_after(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Too many requests',
                ],
                429,
                [
                    'Retry-After' => '120',
                ],
            ),
        ]);

        try {
            $this->client()->get('regions');

            $this->fail(
                'Expected CloudRateLimitException was not thrown.',
            );
        } catch (CloudRateLimitException $exception) {
            $this->assertSame(
                120,
                $exception->retryAfterSeconds,
            );

            $this->assertSame(
                429,
                $exception->getCode(),
            );
        }
    }

    public function test_it_maps_transport_failure(): void
    {
        Http::fake([
            '*' => Http::failedConnection(
                'Connection failed.',
            ),
        ]);

        $this->expectException(
            CloudConnectionException::class,
        );

        $this->expectExceptionMessage(
            'Could not connect to the cloud provider.',
        );

        $this->client()->get('regions');
    }

    public function test_it_rejects_an_empty_path(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->client()->get('');
    }

    public function test_it_rejects_path_traversal(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->client()->get(
            'regions/../secrets',
        );
    }

    public function test_it_rejects_query_string_inside_path(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->client()->get(
            'images?type=distributions',
        );
    }

    public function test_it_requires_https_base_url(): void
    {
        $this->expectException(
            CloudConfigurationException::class,
        );

        new ArvanCloudClient(
            baseUrl: 'http://api.example.test',
            apiKey: 'test-api-key',
            connectTimeout: 5,
            requestTimeout: 15,
        );
    }

    public function test_it_requires_an_api_key(): void
    {
        $this->expectException(
            CloudConfigurationException::class,
        );

        new ArvanCloudClient(
            baseUrl: 'https://api.example.test',
            apiKey: ' ',
            connectTimeout: 5,
            requestTimeout: 15,
        );
    }

    public function test_it_requires_positive_connect_timeout(): void
    {
        $this->expectException(
            CloudConfigurationException::class,
        );

        new ArvanCloudClient(
            baseUrl: 'https://api.example.test',
            apiKey: 'test-api-key',
            connectTimeout: 0,
            requestTimeout: 15,
        );
    }

    public function test_it_requires_positive_request_timeout(): void
    {
        $this->expectException(
            CloudConfigurationException::class,
        );

        new ArvanCloudClient(
            baseUrl: 'https://api.example.test',
            apiKey: 'test-api-key',
            connectTimeout: 5,
            requestTimeout: 0,
        );
    }

    private function client(): ArvanCloudClient
    {
        return new ArvanCloudClient(
            baseUrl: 'https://api.example.test/ecc/v1',
            apiKey: 'test-api-key',
            connectTimeout: 5,
            requestTimeout: 15,
        );
    }
}
