<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Liara;

use App\Domain\Cloud\Exceptions\CloudInsufficientBalanceException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class LiaraCloudClientTest extends TestCase
{
    private const string BASE_URL = 'https://iaas-api.example.test';

    private const string API_TOKEN = 'test-liara-token';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_sends_authenticated_get_request_with_query(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'plans' => [],
            ]),
        ]);

        $this->client()->get(
            'plans',
            [
                'scope' => 'public',
            ],
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::BASE_URL.'/plans?scope=public'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer '.self::API_TOKEN,
                )
                && $request->hasHeader('Accept', 'application/json'),
        );
    }

    public function test_it_sends_create_payload_as_json(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'taskID' => '6a80c1906727f3d124d794cb',
                'VMID' => '6a80c18f6727f3d124d794c6',
            ]),
        ]);

        $payload = [
            'name' => 'cf-liara-test',
            'OS' => 'ubuntu-24.04',
            'plan' => 'standard-base-g2',
            'config' => [
                'SSHKeys' => [],
            ],
        ];

        $result = $this->client()->post('vm', $payload);

        $this->assertSame(
            '6a80c18f6727f3d124d794c6',
            $result['VMID'],
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === self::BASE_URL.'/vm'
                && $request->data() === $payload,
        );
    }

    public function test_it_sends_power_action_with_patch(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response('', 200),
        ]);

        $result = $this->client()->patch(
            'vm/power/6a80c18f6727f3d124d794c6',
            [
                'action' => 'stop',
            ],
        );

        $this->assertSame([], $result);

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'PATCH'
                && $request->data() === [
                    'action' => 'stop',
                ],
        );
    }

    public function test_it_sends_delete_without_request_payload(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response('', 200),
        ]);

        $this->assertSame(
            [],
            $this->client()->delete(
                'vm/6a80c18f6727f3d124d794c6',
            ),
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'DELETE'
                && $request->url() === self::BASE_URL
                .'/vm/6a80c18f6727f3d124d794c6',
        );
    }

    public function test_it_maps_payment_required_to_insufficient_balance(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response('', 402),
        ]);

        $this->expectException(
            CloudInsufficientBalanceException::class,
        );

        $this->client()->post('vm', []);
    }

    #[DataProvider('providerPreconditionStatusProvider')]
    public function test_it_maps_provider_preconditions_to_validation_exception(
        int $status,
    ): void {
        Http::fake([
            self::BASE_URL.'/*' => Http::response('', $status),
        ]);

        try {
            $this->client()->patch(
                'vm/power/6a80c18f6727f3d124d794c6',
                [
                    'action' => 'stop',
                ],
            );

            $this->fail('Expected Liara provider precondition failure.');
        } catch (CloudValidationException $exception) {
            $this->assertSame($status, $exception->getCode());
        }
    }

    public function test_it_maps_not_found_separately_from_bad_request(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response('', 404),
        ]);

        $this->expectException(
            CloudResourceNotFoundException::class,
        );

        $this->client()->get(
            'vm/000000000000000000000000',
        );
    }

    public function test_it_preserves_numeric_retry_after(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response(
                '',
                429,
                [
                    'Retry-After' => '17',
                ],
            ),
        ]);

        try {
            $this->client()->get('plans');

            $this->fail('Expected a rate-limit exception.');
        } catch (CloudRateLimitException $exception) {
            $this->assertSame(17, $exception->retryAfterSeconds);
        }
    }

    public function test_it_does_not_duplicate_bearer_prefix(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'plans' => [],
            ]),
        ]);

        $client = new LiaraCloudClient(
            baseUrl: self::BASE_URL,
            apiToken: 'Bearer '.self::API_TOKEN,
            connectTimeout: 5,
            requestTimeout: 15,
        );

        $client->get('plans');

        Http::assertSent(
            fn (Request $request): bool => $request->hasHeader(
                'Authorization',
                'Bearer '.self::API_TOKEN,
            ),
        );
    }

    public function test_it_rejects_query_string_embedded_in_path(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->client()->get(
            'vm/traffic-volume/abc?startTimeUnix=123',
        );
    }

    /**
     * @return array<string, array{status: int}>
     */
    public static function providerPreconditionStatusProvider(): array
    {
        return [
            'conflict' => ['status' => 409],
            'precondition failed' => ['status' => 412],
            'precondition required' => ['status' => 428],
        ];
    }

    private function client(): LiaraCloudClient
    {
        return new LiaraCloudClient(
            baseUrl: self::BASE_URL,
            apiToken: self::API_TOKEN,
            connectTimeout: 5,
            requestTimeout: 15,
        );
    }
}
