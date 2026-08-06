<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudResponseMapperServerInventoryTest extends TestCase
{
    private const string REGION_ID = 'eu-west1-a';

    private const string DEFAULT_USERNAME = 'ubuntu';

    public function test_it_maps_the_verified_server_inventory_response(): void
    {
        $servers = (new ArvanCloudResponseMapper)->mapServers(
            payload: [
                'data' => [
                    self::server(),
                ],
            ],
            regionId: self::REGION_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertCount(
            1,
            $servers,
        );

        $server = $servers[0];

        $this->assertSame(
            '93b31e1a-aa0b-4594-bf46-bcfef3ca8184',
            $server->id,
        );

        $this->assertSame(
            'xdeploy-e2e-20260806-161430-5itccp',
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
            'eco-1-1-0',
            $server->sizeId,
        );

        $this->assertSame(
            'eco-1-1-0',
            $server->sizeName,
        );

        $this->assertSame(
            1,
            $server->vCpu,
        );

        $this->assertSame(
            1024,
            $server->memoryMiB,
        );

        $this->assertSame(
            25,
            $server->diskGiB,
        );

        $this->assertSame(
            '3236878e-2bdc-4cdd-b082-61b3eeb3f9df',
            $server->imageId,
        );

        $this->assertSame(
            '2026-08-06T16:16:13+00:00',
            $server->createdAt?->format(
                DATE_ATOM,
            ),
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

        $this->assertCount(
            1,
            $server->addresses,
        );

        $this->assertSame(
            '185.204.168.143',
            $server->addresses[0]->address,
        );

        $this->assertTrue(
            $server->addresses[0]->isPublic,
        );
    }

    public function test_it_maps_an_empty_inventory(): void
    {
        $servers = (new ArvanCloudResponseMapper)->mapServers(
            payload: [
                'data' => [],
            ],
            regionId: self::REGION_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertSame(
            [],
            $servers,
        );
    }

    public function test_it_preserves_provider_order(): void
    {
        $first = self::server(
            id: 'server-first',
            name: 'first-server',
        );

        $second = self::server(
            id: 'server-second',
            name: 'second-server',
        );

        $servers = (new ArvanCloudResponseMapper)->mapServers(
            payload: [
                'data' => [
                    $first,
                    $second,
                ],
            ],
            regionId: self::REGION_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertSame(
            [
                'server-first',
                'server-second',
            ],
            array_map(
                static fn ($server): string => $server->id,
                $servers,
            ),
        );
    }

    public function test_it_normalizes_the_region_identifier(): void
    {
        $servers = (new ArvanCloudResponseMapper)->mapServers(
            payload: [
                'data' => [
                    self::server(),
                ],
            ],
            regionId: ' eu-west1-a ',
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertSame(
            self::REGION_ID,
            $servers[0]->regionId,
        );
    }

    public function test_it_rejects_duplicate_server_identifiers(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        (new ArvanCloudResponseMapper)->mapServers(
            payload: [
                'data' => [
                    self::server(),
                    self::server(
                        name: 'duplicate-server',
                    ),
                ],
            ],
            regionId: self::REGION_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    #[DataProvider('invalidInventoryPayloadProvider')]
    public function test_it_rejects_invalid_inventory_payloads(
        array $payload,
    ): void {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        (new ArvanCloudResponseMapper)->mapServers(
            payload: $payload,
            regionId: self::REGION_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidInventoryPayloadProvider(): iterable
    {
        yield 'missing data envelope' => [
            [],
        ];

        yield 'data is not an array' => [
            [
                'data' => 'invalid',
            ],
        ];

        yield 'data is an object' => [
            [
                'data' => self::server(),
            ],
        ];

        yield 'list contains a scalar' => [
            [
                'data' => [
                    'invalid',
                ],
            ],
        ];

        yield 'server is missing identifier' => [
            [
                'data' => [
                    array_diff_key(
                        self::server(),
                        [
                            'id' => true,
                        ],
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function server(
        string $id = '93b31e1a-aa0b-4594-bf46-bcfef3ca8184',
        string $name = 'xdeploy-e2e-20260806-161430-5itccp',
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'flavor' => [
                'id' => 'eco-1-1-0',
                'name' => 'eco-1-1-0',
                'ram' => 1024,
                'swap' => '',
                'vcpus' => 1,
                'disk' => 25,
                'free_server' => false,
            ],
            'status' => 'ACTIVE',
            'image' => [
                'id' => '3236878e-2bdc-4cdd-b082-61b3eeb3f9df',
                'name' => 'Ubuntu',
                'os' => 'ubuntu',
                'os_version' => '26.04',
                'status' => 'active',
                'username' => 'ubuntu',
                'metadata' => [
                    'os_type' => 'linux',
                    'username' => 'ubuntu',
                ],
                'documents' => [],
            ],
            'created' => '2026-08-06T16:16:13Z',
            'password' => '',
            'task_state' => null,
            'key_name' => '',
            'ar_next' => '',
            'security_groups' => [
                [
                    'id' => '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                    'description' => 'New default security group',
                    'name' => 'default',
                    'readonly' => true,
                    'default' => true,
                    'real_name' => 'arDefault',
                    'rules' => null,
                    'ip_addresses' => [
                        '185.204.168.143',
                    ],
                ],
            ],
            'addresses' => [
                'public203' => [
                    [
                        'mac_addr' => 'fa:16:3e:17:a8:55',
                        'version' => '4',
                        'addr' => '185.204.168.143',
                        'type' => 'fixed',
                        'is_public' => true,
                        'is_vpc' => false,
                    ],
                ],
            ],
            'tags' => [],
            'ha_enabled' => false,
            'backup_enabled' => false,
            'cluster_id' => '',
            'spot' => false,
            'networks' => [
                '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
            ],
            'volume_backed' => true,
            'vpcs' => null,
            'domain' => '',
        ];
    }
}
