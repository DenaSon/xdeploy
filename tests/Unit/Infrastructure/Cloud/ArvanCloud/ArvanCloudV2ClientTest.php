<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudV2Client;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ArvanCloudV2ClientTest extends TestCase
{
    private const string BASE_URL =
        'https://api.example.test/ecc/v2';

    private const string API_KEY =
        'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_sends_an_authenticated_get_request(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'data' => null,
            ]),
        ]);

        $result = $this->client()->get(
            'snapshot/eu-west1-a/instance/list',
        );

        $this->assertSame(
            [
                'data' => null,
            ],
            $result,
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'GET'
                    && $request->url() === self::BASE_URL
                    .'/snapshot/eu-west1-a/instance/list'
                    && $request->hasHeader(
                        'Authorization',
                        'Apikey '.self::API_KEY,
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
            self::BASE_URL.'/*' => Http::response([
                'instance_id' => 'server-123',
                'snapshot_id' => 'snapshot-123',
                'message' => 'snapshot created',
            ]),
        ]);

        $payload = [
            'description' => '',
            'instance_id' => 'server-123',
            'name' => 'snapshot-one',
        ];

        $result = $this->client()->post(
            'snapshot/eu-west1-a/instance/create',
            $payload,
        );

        $this->assertSame(
            'snapshot-123',
            $result['snapshot_id'],
        );

        Http::assertSent(
            function (Request $request) use ($payload): bool {
                return $request->method() === 'POST'
                    && $request->url() === self::BASE_URL
                    .'/snapshot/eu-west1-a/instance/create'
                    && $request->hasHeader(
                        'Authorization',
                        'Apikey '.self::API_KEY,
                    )
                    && $request->hasHeader(
                        'Content-Type',
                        'application/json',
                    )
                    && $request->data() === $payload;
            },
        );
    }

    public function test_it_rejects_a_path_with_trailing_slash(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->client()->get(
            'snapshot/eu-west1-a/instance/list/',
        );
    }

    public function test_it_maps_unauthorized_response(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Unauthorized.',
                ],
                401,
            ),
        ]);

        $this->expectException(
            CloudAuthenticationException::class,
        );

        $this->client()->get(
            'snapshot/eu-west1-a/instance/list',
        );
    }

    private function client(): ArvanCloudV2Client
    {
        return new ArvanCloudV2Client(
            baseUrl: self::BASE_URL,
            apiKey: self::API_KEY,
            connectTimeout: 5,
            requestTimeout: 15,
        );
    }
}
