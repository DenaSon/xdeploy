<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ArvanCloudProvisioningTest extends TestCase
{
    private const string BASE_URL =
        'https://api.example.test/ecc/v1';

    private const string REGION_ID =
        'eu-west1-a';

    private const string SERVER_ID =
        'ff83466c-c0fe-4dc4-9d1d-bde29efd0b45';

    private const string SERVER_NAME =
        'xdeploy-test-server';

    private const string GENERATED_PASSWORD =
        'temporary-generated-password';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_creates_a_password_server_from_minimal_create_response(): void
    {
        Http::fake([
            $this->serverCollectionUrl() => Http::response(
                [
                    'id' => self::SERVER_ID,
                    'password' => self::GENERATED_PASSWORD,
                ],
                201,
            ),
        ]);

        $created = $this->provider()->createServer(
            $this->createData(),
        );

        $this->assertSame(
            self::SERVER_ID,
            $created->id,
        );

        $this->assertSame(
            self::SERVER_NAME,
            $created->name,
        );

        $this->assertSame(
            self::REGION_ID,
            $created->regionId,
        );

        $this->assertSame(
            CloudServerStatus::Provisioning,
            $created->status,
        );

        $this->assertSame(
            'ubuntu',
            $created->username,
        );

        $this->assertNull(
            $created->createdAt,
        );

        $this->assertSame(
            self::GENERATED_PASSWORD,
            $created->generatedPassword(),
        );

        $this->assertTrue(
            $created->hasGeneratedPassword(),
        );

        $this->assertArrayNotHasKey(
            'generated_password',
            $created->toArray(),
        );

        $this->assertArrayNotHasKey(
            'password',
            $created->toArray(),
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'POST'
                    && $request->url() ===
                    $this->serverCollectionUrl()
                    && $request->data() === [
                        'name' => self::SERVER_NAME,

                        'network_id' => 'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',

                        'flavor_id' => 'eco-1-1-0',

                        'image_id' => '00aaa9d1-3e0a-468c-aaf4-334513981e42',

                        'security_groups' => [
                            [
                                'name' => '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                            ],
                        ],

                        'ssh_key' => false,
                        'key_name' => null,
                        'count' => 1,
                        'create_type' => 'cinder',
                        'disk_size' => 25,
                        'init_script' => '',
                        'ha_enabled' => false,
                    ];
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_finds_an_active_server_using_direct_endpoint(): void
    {
        Http::fake([
            $this->serverDetailsUrl() => Http::response([
                'data' => $this->serverObject(),
            ]),
        ]);

        $server = $this->provider()->findServer(
            self::REGION_ID,
            self::SERVER_ID,
        );

        $this->assertInstanceOf(
            CloudServerData::class,
            $server,
        );

        $this->assertSame(
            self::SERVER_ID,
            $server->id,
        );

        $this->assertSame(
            self::SERVER_NAME,
            $server->name,
        );

        $this->assertSame(
            self::REGION_ID,
            $server->regionId,
        );

        $this->assertSame(
            CloudServerStatus::Active,
            $server->status,
        );

        $this->assertSame(
            CloudServerPowerState::Running,
            $server->powerState,
        );

        $this->assertSame(
            'ubuntu',
            $server->username,
        );

        $this->assertSame(
            'eco-2-2-0',
            $server->sizeId,
        );

        $this->assertSame(
            'eco-small4',
            $server->sizeName,
        );

        $this->assertSame(
            2,
            $server->vCpu,
        );

        $this->assertSame(
            2048,
            $server->memoryMiB,
        );

        $this->assertSame(
            50,
            $server->diskGiB,
        );

        $this->assertSame(
            [
                '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
            ],
            $server->networkIds,
        );

        $this->assertSame(
            [
                '8449a4f5-5709-4017-9e63-45496bfe5cc9',
            ],
            $server->securityGroupIds,
        );

        $this->assertSame(
            [
                '185.204.168.213',
            ],
            $server->publicIpv4s(),
        );

        $this->assertTrue(
            $server->hasPublicIpv4(),
        );

        $this->assertTrue(
            $server->isRunning(),
        );

        $this->assertTrue(
            $server->isReadyForSshCheck(),
        );

        $this->assertNull(
            $server->taskState,
        );

        $this->assertNull(
            $server->providerError,
        );

        $this->assertArrayNotHasKey(
            'password',
            $server->toArray(),
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'GET'
                    && $request->url() ===
                    $this->serverDetailsUrl();
            },
        );

        Http::assertNotSent(
            function (Request $request): bool {
                return $request->method() === 'GET'
                    && $request->url() ===
                    $this->serverCollectionUrl();
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_accepts_a_direct_server_object_without_data_envelope(): void
    {
        Http::fake([
            $this->serverDetailsUrl() => Http::response(
                $this->serverObject(),
            ),
        ]);

        $server = $this->provider()->findServer(
            self::REGION_ID,
            self::SERVER_ID,
        );

        $this->assertSame(
            self::SERVER_ID,
            $server->id,
        );

        $this->assertSame(
            CloudServerPowerState::Running,
            $server->powerState,
        );

        Http::assertSentCount(1);
    }

    public function test_it_finds_a_stopped_server_using_direct_endpoint(): void
    {
        Http::fake([
            $this->serverDetailsUrl() => Http::response([
                'data' => $this->serverObject([
                    'status' => 'SHUTOFF',
                ]),
            ]),
        ]);

        $server = $this->provider()->findServer(
            self::REGION_ID,
            self::SERVER_ID,
        );

        $this->assertSame(
            CloudServerStatus::Active,
            $server->status,
        );

        $this->assertSame(
            CloudServerPowerState::Stopped,
            $server->powerState,
        );

        $this->assertTrue(
            $server->isStopped(),
        );

        $this->assertFalse(
            $server->isRunning(),
        );

        $this->assertFalse(
            $server->isReadyForSshCheck(),
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() ===
                $this->serverDetailsUrl(),
        );

        Http::assertSentCount(1);
    }

    public function test_it_maps_server_operation_state_and_provider_error(): void
    {
        Http::fake([
            $this->serverDetailsUrl() => Http::response([
                'data' => $this->serverObject([
                    'status' => 'ERROR',
                    'task_state' => 'resize_failed',
                    'error' => 'Unable to resize server.',
                ]),
            ]),
        ]);

        $server = $this->provider()->findServer(
            self::REGION_ID,
            self::SERVER_ID,
        );

        $this->assertSame(
            CloudServerStatus::Failed,
            $server->status,
        );

        $this->assertSame(
            CloudServerPowerState::Error,
            $server->powerState,
        );

        $this->assertSame(
            'resize_failed',
            $server->taskState,
        );

        $this->assertSame(
            'Unable to resize server.',
            $server->providerError,
        );

        $this->assertTrue(
            $server->hasProviderError(),
        );

        $this->assertFalse(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_it_preserves_all_public_ips_and_deduplicates_references(): void
    {
        $serverObject = $this->serverObject([
            'addresses' => [
                'Default network' => [
                    [
                        'addr' => '185.204.168.213',
                        'version' => 4,
                        'is_public' => true,
                        'is_vpc' => false,
                        'type' => 'fixed',
                    ],
                    [
                        'addr' => '185.204.168.213',
                        'version' => 4,
                        'is_public' => true,
                        'is_vpc' => false,
                        'type' => 'fixed',
                    ],
                    [
                        'addr' => '185.204.171.249',
                        'version' => 4,
                        'is_public' => true,
                        'is_vpc' => false,
                        'type' => 'fixed',
                    ],
                    [
                        'addr' => '10.0.0.5',
                        'version' => 4,
                        'is_public' => false,
                        'is_vpc' => true,
                        'type' => 'fixed',
                    ],
                ],
            ],

            'networks' => [
                '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
                '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
            ],

            'security_groups' => [
                [
                    'id' => '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                ],
                [
                    'id' => '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                ],
            ],
        ]);

        Http::fake([
            $this->serverDetailsUrl() => Http::response([
                'data' => $serverObject,
            ]),
        ]);

        $server = $this->provider()->findServer(
            self::REGION_ID,
            self::SERVER_ID,
        );

        $this->assertSame(
            [
                '185.204.168.213',
                '185.204.171.249',
            ],
            $server->publicIpv4s(),
        );

        $this->assertSame(
            [
                '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
            ],
            $server->networkIds,
        );

        $this->assertSame(
            [
                '8449a4f5-5709-4017-9e63-45496bfe5cc9',
            ],
            $server->securityGroupIds,
        );

        Http::assertSentCount(1);
    }

    public function test_it_maps_direct_server_not_found_response(): void
    {
        Http::fake([
            $this->serverDetailsUrl(
                'missing-server-id',
            ) => Http::response(
                [
                    'message' => 'Server not found.',
                ],
                404,
            ),
        ]);

        try {
            $this->provider()->findServer(
                self::REGION_ID,
                'missing-server-id',
            );

            $this->fail(
                'Expected CloudResourceNotFoundException was not thrown.',
            );
        } catch (CloudResourceNotFoundException $exception) {
            $this->assertSame(
                404,
                $exception->getCode(),
            );

            $this->assertSame(
                'Cloud provider resource was not found.',
                $exception->getMessage(),
            );
        }

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() ===
                $this->serverDetailsUrl(
                    'missing-server-id',
                ),
        );

        Http::assertSentCount(1);
    }

    public function test_it_maps_direct_server_provider_failure(): void
    {
        Http::fake([
            $this->serverDetailsUrl() => Http::response(
                [
                    'message' => 'Internal provider error.',
                ],
                500,
            ),
        ]);

        try {
            $this->provider()->findServer(
                self::REGION_ID,
                self::SERVER_ID,
            );

            $this->fail(
                'Expected CloudConnectionException was not thrown.',
            );
        } catch (CloudConnectionException $exception) {
            $this->assertSame(
                500,
                $exception->getCode(),
            );

            $this->assertSame(
                'Cloud provider is temporarily unavailable.',
                $exception->getMessage(),
            );
        }

        Http::assertSentCount(1);
    }

    public function test_it_rejects_invalid_server_identifier_before_request(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->findServer(
                self::REGION_ID,
                '../invalid-server-id',
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_requires_a_generated_password_for_password_flow(): void
    {
        Http::fake([
            $this->serverCollectionUrl() => Http::response(
                [
                    'id' => self::SERVER_ID,
                ],
                201,
            ),
        ]);

        try {
            $this->provider()->createServer(
                $this->createData(),
            );

            $this->fail(
                'Expected CloudUnexpectedResponseException was not thrown.',
            );
        } catch (CloudUnexpectedResponseException $exception) {
            $this->assertSame(
                'ArvanCloud create response did not contain a generated password.',
                $exception->getMessage(),
            );
        }

        Http::assertSentCount(1);
    }

    public function test_it_uses_response_name_when_create_response_contains_name(): void
    {
        Http::fake([
            $this->serverCollectionUrl() => Http::response(
                [
                    'id' => self::SERVER_ID,
                    'name' => 'provider-returned-name',
                    'password' => self::GENERATED_PASSWORD,
                ],
                201,
            ),
        ]);

        $created = $this->provider()->createServer(
            $this->createData(),
        );

        $this->assertSame(
            'provider-returned-name',
            $created->name,
        );

        $this->assertSame(
            CloudServerStatus::Provisioning,
            $created->status,
        );

        $this->assertNull(
            $created->createdAt,
        );

        Http::assertSentCount(1);
    }

    public function test_it_uses_requested_name_when_create_response_contains_empty_name(): void
    {
        Http::fake([
            $this->serverCollectionUrl() => Http::response(
                [
                    'id' => self::SERVER_ID,
                    'name' => '   ',
                    'password' => self::GENERATED_PASSWORD,
                ],
                201,
            ),
        ]);

        $created = $this->provider()->createServer(
            $this->createData(),
        );

        $this->assertSame(
            self::SERVER_NAME,
            $created->name,
        );

        Http::assertSentCount(1);
    }

    private function provider(): ArvanCloudProvider
    {
        return new ArvanCloudProvider(
            client: new ArvanCloudClient(
                baseUrl: self::BASE_URL,
                apiKey: 'test-api-key',
                connectTimeout: 5,
                requestTimeout: 15,
            ),

            mapper: new ArvanCloudResponseMapper,

            createType: 'cinder',

            defaultUsername: 'ubuntu',
        );
    }

    private function createData(): CreateCloudServerData
    {
        return new CreateCloudServerData(
            name: self::SERVER_NAME,

            regionId: self::REGION_ID,

            sizeId: 'eco-1-1-0',

            imageId: '00aaa9d1-3e0a-468c-aaf4-334513981e42',

            networkId: 'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',

            securityGroupIds: [
                '8449a4f5-5709-4017-9e63-45496bfe5cc9',
            ],

            diskGiB: 25,

            sshKeyName: null,

            initializationScript: '',

            highAvailability: false,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function serverObject(
        array $overrides = [],
    ): array {
        $server = [
            'id' => self::SERVER_ID,
            'name' => self::SERVER_NAME,
            'status' => 'ACTIVE',
            'username' => 'ubuntu',

            'flavor' => [
                'id' => 'eco-2-2-0',
                'name' => 'eco-small4',
                'vcpus' => 2,
                'ram' => 2048,
                'disk' => 50,
            ],

            'image' => [
                'id' => '00aaa9d1-3e0a-468c-aaf4-334513981e42',

                'username' => 'ubuntu',
            ],

            'created' => '2026-08-04T18:14:54+00:00',

            'addresses' => [
                'Default network' => [
                    [
                        'addr' => '185.204.168.213',
                        'version' => 4,
                        'is_public' => true,
                        'is_vpc' => false,
                        'type' => 'fixed',
                    ],
                ],
            ],

            'networks' => [
                '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
            ],

            'security_groups' => [
                [
                    'id' => '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                ],
            ],

            'volume_backed' => true,
            'ha_enabled' => false,
            'task_state' => null,
            'error' => null,
        ];

        return array_replace(
            $server,
            $overrides,
        );
    }

    private function serverCollectionUrl(): string
    {
        return sprintf(
            '%s/regions/%s/servers',
            self::BASE_URL,
            self::REGION_ID,
        );
    }

    private function serverDetailsUrl(
        string $serverId = self::SERVER_ID,
    ): string {
        return sprintf(
            '%s/regions/%s/servers/%s',
            self::BASE_URL,
            self::REGION_ID,
            $serverId,
        );
    }
}
