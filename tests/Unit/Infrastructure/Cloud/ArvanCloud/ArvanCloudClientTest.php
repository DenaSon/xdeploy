<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudInsufficientBalanceException;
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
            'https://api.example.test/ecc/v1/*' =>
                Http::response([
                    'data' => [],
                ]),
        ]);

        $result = $this->client()->get(
            'regions/eu-west1-a/images',
            [
                'type' => 'distributions',
            ],
        );

        $this->assertSame(
            [
                'data' => [],
            ],
            $result,
        );

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

    public function test_it_sends_an_authenticated_post_request(): void
    {
        Http::fake([
            'https://api.example.test/ecc/v1/*' =>
                Http::response(
                    [
                        'id' => 'cloud-server-id',
                        'status' => 'BUILD',
                    ],
                    201,
                ),
        ]);

        $payload = [
            'name' => 'xdeploy-server',
            'network_id' => 'network-id',
            'flavor_id' => 'eco-1-1-0',
            'image_id' => 'ubuntu-image',
        ];

        $result = $this->client()->post(
            'regions/eu-west1-a/servers',
            $payload,
        );

        $this->assertSame(
            'cloud-server-id',
            $result['id'],
        );

        $this->assertSame(
            'BUILD',
            $result['status'],
        );

        Http::assertSent(
            function (Request $request) use ($payload): bool {
                return $request->method() === 'POST'
                    && $request->url() ===
                    'https://api.example.test/ecc/v1/regions/eu-west1-a/servers'
                    && $request->hasHeader(
                        'Authorization',
                        'Apikey test-api-key',
                    )
                    && $request->hasHeader(
                        'Accept',
                        'application/json',
                    )
                    && $request->hasHeader(
                        'Content-Type',
                        'application/json',
                    )
                    && $request->data() === $payload;
            },
        );
    }

    public function test_it_sends_post_request_without_payload(): void
    {
        Http::fake([
            'https://api.example.test/ecc/v1/*' =>
                Http::response(
                    [
                        'message' => 'Server is powering on.',
                    ],
                    202,
                ),
        ]);

        $result = $this->client()->post(
            'regions/eu-west1-a/servers/server-123/power-on',
        );

        $this->assertSame(
            'Server is powering on.',
            $result['message'],
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'POST'
                    && $request->url() ===
                    'https://api.example.test/ecc/v1/regions/eu-west1-a/servers/server-123/power-on'
                    && $request->data() === [];
            },
        );
    }

    public function test_it_sends_an_authenticated_delete_request(): void
    {
        Http::fake([
            'https://api.example.test/ecc/v1/*' =>
                Http::response([
                    'message' => 'Port deleted.',
                ]),
        ]);

        $result = $this->client()->delete(
            'regions/eu-west1-a/ports/port-123',
        );

        $this->assertSame(
            'Port deleted.',
            $result['message'],
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'DELETE'
                    && $request->url() ===
                    'https://api.example.test/ecc/v1/regions/eu-west1-a/ports/port-123'
                    && $request->hasHeader(
                        'Authorization',
                        'Apikey test-api-key',
                    )
                    && $request->hasHeader(
                        'Accept',
                        'application/json',
                    )
                    && $request->hasHeader(
                        'Content-Type',
                        'application/json',
                    )
                    && $request->data() === [];
            },
        );
    }

    public function test_it_sends_payload_with_delete_request(): void
    {
        Http::fake([
            'https://api.example.test/ecc/v1/*' =>
                Http::response([
                    'message' => 'Resource deleted.',
                ]),
        ]);

        $payload = [
            'force' => true,
        ];

        $this->client()->delete(
            'regions/eu-west1-a/resources/resource-123',
            $payload,
        );

        Http::assertSent(
            function (Request $request) use ($payload): bool {
                return $request->method() === 'DELETE'
                    && $request->data() === $payload;
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
            fn (Request $request): bool =>
            ! $request->hasHeader(
                'Authorization',
                'Bearer test-api-key',
            ),
        );
    }

    public function test_it_prevents_duplicate_apikey_prefix(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [],
            ]),
        ]);

        $client = new ArvanCloudClient(
            baseUrl: 'https://api.example.test/ecc/v1',
            apiKey: 'Apikey test-api-key',
            connectTimeout: 5,
            requestTimeout: 15,
        );

        $client->get('regions');

        Http::assertSent(
            function (Request $request): bool {
                return $request->hasHeader(
                        'Authorization',
                        'Apikey test-api-key',
                    )
                    && ! $request->hasHeader(
                        'Authorization',
                        'Apikey Apikey test-api-key',
                    );
            },
        );
    }

    public function test_it_removes_trailing_slash_from_base_url(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [],
            ]),
        ]);

        $client = new ArvanCloudClient(
            baseUrl: 'https://api.example.test/ecc/v1/',
            apiKey: 'test-api-key',
            connectTimeout: 5,
            requestTimeout: 15,
        );

        $client->get('regions');

        Http::assertSent(
            fn (Request $request): bool =>
                $request->url() ===
                'https://api.example.test/ecc/v1/regions',
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

        $result = $this->client()->get(
            'regions',
        );

        $this->assertSame(
            'eu-west1-a',
            $result['data'][0]['id'],
        );
    }

    public function test_it_accepts_successful_202_response(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Operation accepted.',
                ],
                202,
            ),
        ]);

        $result = $this->client()->post(
            'regions/eu-west1-a/servers/server-123/reboot',
        );

        $this->assertSame(
            'Operation accepted.',
            $result['message'],
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

        $this->expectExceptionMessage(
            'Cloud provider returned an unexpected JSON payload.',
        );

        $this->client()->get('regions');
    }

    #[DataProvider('mappedStatusProvider')]
    public function test_it_maps_http_statuses_to_cloud_exceptions(
        int $status,
        string $expectedException,
    ): void {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Provider error.',
                ],
                $status,
            ),
        ]);

        $this->expectException(
            $expectedException,
        );

        $this->client()->get('regions');
    }

    /**
     * @return array<string, array{
     *     int,
     *     class-string<\Throwable>
     * }>
     */
    public static function mappedStatusProvider(): array
    {
        return [
            'redirect' => [
                302,
                CloudUnexpectedResponseException::class,
            ],

            'bad request' => [
                400,
                CloudValidationException::class,
            ],

            'unauthorized' => [
                401,
                CloudAuthenticationException::class,
            ],

            'insufficient balance' => [
                402,
                CloudInsufficientBalanceException::class,
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

            'terminated server' => [
                419,
                CloudValidationException::class,
            ],

            'unprocessable content' => [
                422,
                CloudValidationException::class,
            ],

            'rate limited' => [
                429,
                CloudRateLimitException::class,
            ],

            'server error' => [
                500,
                CloudConnectionException::class,
            ],

            'service unavailable' => [
                503,
                CloudConnectionException::class,
            ],
        ];
    }

    public function test_it_maps_insufficient_balance_response(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' =>
                        'Account balance is insufficient.',
                ],
                402,
            ),
        ]);

        try {
            $this->client()->post(
                'regions/eu-west1-a/servers',
                [
                    'name' => 'xdeploy-server',
                ],
            );

            $this->fail(
                'Expected insufficient balance exception was not thrown.',
            );
        } catch (
        CloudInsufficientBalanceException $exception
        ) {
            $this->assertSame(
                402,
                $exception->getCode(),
            );

            $this->assertSame(
                'Cloud provider account balance is insufficient.',
                $exception->getMessage(),
            );

            $this->assertStringNotContainsString(
                'Account balance is insufficient.',
                $exception->getMessage(),
            );
        }
    }

    public function test_it_maps_rate_limit_and_numeric_retry_after(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Too many requests.',
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
                'Expected rate limit exception was not thrown.',
            );
        } catch (
        CloudRateLimitException $exception
        ) {
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

    public function test_it_returns_null_when_retry_after_is_missing(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Too many requests.',
                ],
                429,
            ),
        ]);

        try {
            $this->client()->get('regions');

            $this->fail(
                'Expected rate limit exception was not thrown.',
            );
        } catch (
        CloudRateLimitException $exception
        ) {
            $this->assertNull(
                $exception->retryAfterSeconds,
            );
        }
    }

    public function test_it_maps_get_transport_failure(): void
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

    public function test_it_maps_post_transport_failure(): void
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

        $this->client()->post(
            'regions/eu-west1-a/servers',
            [
                'name' => 'xdeploy-server',
            ],
        );
    }

    public function test_it_maps_delete_transport_failure(): void
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

        $this->client()->delete(
            'regions/eu-west1-a/ports/port-123',
        );
    }

    #[DataProvider('invalidPathProvider')]
    public function test_it_rejects_invalid_request_paths(
        string $path,
    ): void {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->client()->get(
            $path,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPathProvider(): array
    {
        return [
            'empty path' => [
                '',
            ],

            'whitespace path' => [
                '   ',
            ],

            'root path' => [
                '/',
            ],

            'path traversal' => [
                'regions/../secrets',
            ],

            'encoded path traversal' => [
                'regions/%2E%2E/secrets',
            ],

            'query string inside path' => [
                'images?type=distributions',
            ],

            'fragment inside path' => [
                'regions#fragment',
            ],

            'backslash inside path' => [
                'regions\secrets',
            ],

            'double slash' => [
                'regions//images',
            ],
        ];
    }

    public function test_it_allows_a_leading_path_slash(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [],
            ]),
        ]);

        $this->client()->get(
            '/regions',
        );

        Http::assertSent(
            fn (Request $request): bool =>
                $request->url() ===
                'https://api.example.test/ecc/v1/regions',
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

    #[DataProvider('invalidBaseUrlProvider')]
    public function test_it_rejects_invalid_base_urls(
        string $baseUrl,
    ): void {
        $this->expectException(
            CloudConfigurationException::class,
        );

        new ArvanCloudClient(
            baseUrl: $baseUrl,
            apiKey: 'test-api-key',
            connectTimeout: 5,
            requestTimeout: 15,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidBaseUrlProvider(): array
    {
        return [
            'empty URL' => [
                '',
            ],

            'missing host' => [
                'https://',
            ],

            'URL with username' => [
                'https://user@api.example.test',
            ],

            'URL with password' => [
                'https://user:password@api.example.test',
            ],

            'URL with query string' => [
                'https://api.example.test?region=eu',
            ],

            'URL with fragment' => [
                'https://api.example.test#fragment',
            ],
        ];
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

    public function test_it_rejects_api_key_containing_whitespace(): void
    {
        $this->expectException(
            CloudConfigurationException::class,
        );

        new ArvanCloudClient(
            baseUrl: 'https://api.example.test',
            apiKey: 'invalid api key',
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
