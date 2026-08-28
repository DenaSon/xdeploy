<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ParsPack;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ParsPack\ParsPackCloudClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ParsPackCloudClientTest extends TestCase
{
    private const string BASE_URL = 'https://my.parspack.example.test/cserver/api/public/v1';
    private const string API_TOKEN = 'test-parspack-token';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_it_sends_bearer_authenticated_catalog_request(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response(['data' => []]),
        ]);

        $this->client()->getCatalog('regions', [
            'page' => 1,
            'per_page' => 100,
        ]);

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::BASE_URL.'/regions?page=1&per_page=100'
                && $request->hasHeader('Authorization', 'Bearer '.self::API_TOKEN)
                && $request->hasHeader('Accept', 'application/json'),
        );
    }

    public function test_it_accepts_201_create_response_and_sends_json(): void
    {
        $payload = [
            'name' => 'coreflare-test',
            'region' => 'frankfurt',
            'size' => 'deVPS2',
            'image' => 'ubuntu24-cloudinit-qcow2',
            'ssh_keys' => [14956],
            'backups' => false,
            'ipv6' => false,
        ];

        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'id' => 'a34a-4c03-5f73-1a14',
                'name' => 'coreflare-test',
                'status' => 'new',
            ], 201),
        ]);

        $result = $this->client()->post('vms', $payload);

        $this->assertSame('a34a-4c03-5f73-1a14', $result['id']);

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === self::BASE_URL.'/vms'
                && $request->data() === $payload
                && $request->hasHeader('Content-Type', 'application/json'),
        );
    }

    public function test_delete_204_maps_to_empty_payload(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response('', 204),
        ]);

        $this->assertSame(
            [],
            $this->client()->delete('vms/a34a-4c03-5f73-1a14'),
        );
    }

    public function test_actual_authentication_error_shape_maps_to_authentication_exception(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'success' => false,
                'message' => 'دسترسی غیر مجاز',
                'code' => 401,
                'error_code' => 30002,
            ], 401),
        ]);

        $this->expectException(CloudAuthenticationException::class);
        $this->client()->get('regions');
    }

    public function test_actual_validation_error_message_is_preserved(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'id' => 'unprocessable_entity',
                'message' => 'نام نباید بیش از 30 کاراکتر باشد.',
                'code' => 0,
            ], 422),
        ]);

        try {
            $this->client()->post('vms', []);
            $this->fail('Expected ParsPack validation failure.');
        } catch (CloudValidationException $exception) {
            $this->assertSame(422, $exception->getCode());
            $this->assertStringContainsString(
                'نام نباید بیش از 30 کاراکتر باشد.',
                $exception->getMessage(),
            );
        }
    }

    public function test_generic_provider_500_is_not_classified_as_insufficient_balance(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'id' => 'server_error',
                'message' => 'An unexpected error occurred.',
                'code' => 0,
            ], 500),
        ]);

        $this->expectException(CloudConnectionException::class);
        $this->client()->post('vms', []);
    }

    private function client(): ParsPackCloudClient
    {
        return new ParsPackCloudClient(
            baseUrl: self::BASE_URL,
            apiToken: self::API_TOKEN,
            retryMaxAttempts: 1,
        );
    }
}
