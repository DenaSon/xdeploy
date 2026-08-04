<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use JsonException;
use Tests\TestCase;

final class ArvanCloudResponseMapperTest extends TestCase
{
    private const REGION_ID = 'eu-west1-a';

    private const SERVER_ID = 'provider-server-id';

    private ArvanCloudResponseMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new ArvanCloudResponseMapper;
    }

    public function test_it_maps_regions(): void
    {
        $regions = $this->mapper->mapRegions(
            $this->fixture('regions.json'),
        );

        $region = $this->findById(
            $regions,
            self::REGION_ID,
        );

        $this->assertInstanceOf(
            CloudRegionData::class,
            $region,
        );

        $this->assertSame(
            'Germany / Karlsruhe / Goethe',
            $region->displayName,
        );

        $this->assertSame(
            'Germany',
            $region->country,
        );

        $this->assertSame(
            'Karlsruhe',
            $region->city,
        );

        $this->assertSame(
            'Goethe',
            $region->dataCenter,
        );

        $this->assertTrue(
            $region->canCreateServers,
        );

        $this->assertTrue(
            $region->isVisible,
        );

        $this->assertTrue(
            $region->supportsVolumeBacked,
        );
    }

    public function test_it_maps_minimal_create_response(): void
    {
        $createdServer = $this->mapper->mapCreatedServer(
            payload: [
                'data' => [
                    'id' => self::SERVER_ID,
                    'password' => 'generated-password',
                ],
            ],
            regionId: self::REGION_ID,
            defaultUsername: 'ubuntu',
            requestedName: 'xdeploy-server',
        );

        $this->assertSame(
            self::SERVER_ID,
            $createdServer->id,
        );

        $this->assertSame(
            'xdeploy-server',
            $createdServer->name,
        );

        $this->assertSame(
            self::REGION_ID,
            $createdServer->regionId,
        );

        $this->assertSame(
            CloudServerStatus::Provisioning,
            $createdServer->status,
        );

        $this->assertSame(
            'ubuntu',
            $createdServer->username,
        );

        $this->assertNull(
            $createdServer->createdAt,
        );

        $this->assertSame(
            'generated-password',
            $createdServer->generatedPassword(),
        );
    }

    public function test_it_uses_response_name_when_create_response_contains_one(): void
    {
        $createdServer = $this->mapper->mapCreatedServer(
            payload: [
                'data' => [
                    'id' => self::SERVER_ID,
                    'name' => 'provider-server-name',
                    'password' => 'generated-password',
                ],
            ],
            regionId: self::REGION_ID,
            defaultUsername: 'ubuntu',
            requestedName: 'requested-server-name',
        );

        $this->assertSame(
            'provider-server-name',
            $createdServer->name,
        );
    }

    public function test_it_rejects_empty_requested_server_name(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'Requested cloud server name cannot be empty.',
        );

        $this->mapper->mapCreatedServer(
            payload: [
                'data' => [
                    'id' => self::SERVER_ID,
                    'password' => 'generated-password',
                ],
            ],
            regionId: self::REGION_ID,
            defaultUsername: 'ubuntu',
            requestedName: '   ',
        );
    }

    public function test_it_maps_sizes_and_exact_prices(): void
    {
        $sizes = $this->mapper->mapSizes(
            $this->fixture('sizes.json'),
            self::REGION_ID,
        );

        $size = $this->findById(
            $sizes,
            'eco-2-2-0',
        );

        $this->assertInstanceOf(
            CloudSizeData::class,
            $size,
        );

        $this->assertSame(
            'eco-small4',
            $size->name,
        );

        $this->assertSame(
            self::REGION_ID,
            $size->regionId,
        );

        $this->assertSame(
            2,
            $size->vCpu,
        );

        $this->assertSame(
            2048,
            $size->memoryMiB,
        );

        $this->assertSame(
            30,
            $size->diskGiB,
        );

        $this->assertSame(
            'economic',
            $size->category,
        );

        $this->assertNotNull(
            $size->hourlyPrice,
        );

        $this->assertSame(
            '23200',
            $size->hourlyPrice->amount,
        );

        $this->assertNull(
            $size->hourlyPrice->currencyCode,
        );

        $this->assertSame(
            CloudBillingPeriod::Hourly,
            $size->hourlyPrice->billingPeriod,
        );

        $this->assertNotNull(
            $size->monthlyPrice,
        );

        $this->assertSame(
            '16704000',
            $size->monthlyPrice->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Monthly,
            $size->monthlyPrice->billingPeriod,
        );
    }

    public function test_it_flattens_and_maps_images(): void
    {
        $images = $this->mapper->mapImages(
            $this->fixture('images.json'),
            self::REGION_ID,
        );

        $image = $this->findById(
            $images,
            '00aaa9d1-3e0a-468c-aaf4-334513981e42',
        );

        $this->assertInstanceOf(
            CloudImageData::class,
            $image,
        );

        $this->assertSame(
            'Ubuntu 24.04',
            $image->name,
        );

        $this->assertSame(
            'Ubuntu',
            $image->distribution,
        );

        $this->assertSame(
            '24.04',
            $image->version,
        );

        $this->assertSame(
            self::REGION_ID,
            $image->regionId,
        );

        $this->assertNull(
            $image->architecture,
        );

        $this->assertNull(
            $image->minDiskGiB,
        );

        $this->assertNull(
            $image->minMemoryMiB,
        );

        $this->assertTrue(
            $image->supportsSshKey,
        );

        $this->assertTrue(
            $image->supportsPassword,
        );
    }

    public function test_it_maps_network_from_primary_subnet(): void
    {
        $networks = $this->mapper->mapNetworks(
            $this->fixture('networks.json'),
            self::REGION_ID,
        );

        $network = $this->findById(
            $networks,
            'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',
        );

        $this->assertInstanceOf(
            CloudNetworkData::class,
            $network,
        );

        $this->assertSame(
            CloudIpVersion::IPv4,
            $network->ipVersion,
        );

        $this->assertSame(
            '130.185.120.0/22',
            $network->cidr,
        );

        $this->assertSame(
            '130.185.120.1',
            $network->gateway,
        );

        $this->assertTrue(
            $network->isActive,
        );

        $this->assertTrue(
            $network->dhcpEnabled,
        );
    }

    public function test_it_maps_security_groups_without_provider_fields(): void
    {
        $groups = $this->mapper->mapSecurityGroups(
            $this->fixture('security-groups.json'),
            self::REGION_ID,
        );

        $group = $this->findById(
            $groups,
            '8449a4f5-5709-4017-9e63-45496bfe5cc9',
        );

        $this->assertInstanceOf(
            CloudSecurityGroupData::class,
            $group,
        );

        $this->assertSame(
            'default',
            $group->name,
        );

        $this->assertTrue(
            $group->isDefault,
        );

        $this->assertTrue(
            $group->isReadOnly,
        );

        $this->assertFalse(
            property_exists(
                $group,
                'realName',
            ),
        );

        $this->assertFalse(
            property_exists(
                $group,
                'rules',
            ),
        );
    }

    public function test_it_maps_quota_limits_and_usage(): void
    {
        $quota = $this->mapper->mapQuota(
            $this->fixture('quota.json'),
            self::REGION_ID,
        );

        $this->assertSame(
            self::REGION_ID,
            $quota->regionId,
        );

        $this->assertSame(
            1000,
            $quota->instancesLimit,
        );

        $this->assertSame(
            0,
            $quota->instancesUsed,
        );

        $this->assertSame(
            8000,
            $quota->vCpuLimit,
        );

        $this->assertSame(
            0,
            $quota->vCpuUsed,
        );

        $this->assertSame(
            13_107_200,
            $quota->memoryMiBLimit,
        );

        $this->assertSame(
            0,
            $quota->memoryMiBUsed,
        );

        $this->assertSame(
            1600,
            $quota->sshKeysLimit,
        );

        $this->assertNull(
            $quota->sshKeysUsed,
        );
    }

    public function test_it_maps_active_server_with_real_provider_reference_shapes(): void
    {
        $server = $this->mapper->mapServer(
            payload: $this->serverPayload(),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'ubuntu',
        );

        $this->assertSame(
            self::SERVER_ID,
            $server->id,
        );

        $this->assertSame(
            'xdeploy-server',
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
            'ubuntu',
            $server->username,
        );

        $this->assertSame(
            'eco-2-1-0',
            $server->sizeId,
        );

        $this->assertSame(
            '3236878e-2bdc-4cdd-b082-61b3eeb3f9df',
            $server->imageId,
        );

        $this->assertNotNull(
            $server->createdAt,
        );

        $this->assertCount(
            1,
            $server->addresses,
        );

        $address = $server->addresses[0];

        $this->assertSame(
            '185.204.169.89',
            $address->address,
        );

        $this->assertSame(
            CloudIpVersion::IPv4,
            $address->version,
        );

        $this->assertTrue(
            $address->isPublic,
        );

        $this->assertFalse(
            $address->isVpc,
        );

        $this->assertSame(
            'fixed',
            $address->type,
        );

        $this->assertSame(
            [
                'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',
            ],
            $server->networkIds,
        );

        $this->assertSame(
            [
                '8449a4f5-5709-4017-9e63-45496bfe5cc9',
            ],
            $server->securityGroupIds,
        );

        $this->assertTrue(
            $server->volumeBacked,
        );

        $this->assertFalse(
            $server->highAvailability,
        );
    }

    public function test_it_deduplicates_server_networks_and_security_groups(): void
    {
        $payload = $this->serverPayload([
            'networks' => [
                'network-id',
                'network-id',
            ],
            'security_groups' => [
                [
                    'id' => 'security-group-id',
                ],
                [
                    'id' => 'security-group-id',
                ],
            ],
        ]);

        $server = $this->mapper->mapServer(
            payload: $payload,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'ubuntu',
        );

        $this->assertSame(
            ['network-id'],
            $server->networkIds,
        );

        $this->assertSame(
            ['security-group-id'],
            $server->securityGroupIds,
        );
    }

    public function test_it_tolerates_incomplete_provider_fields_while_server_is_provisioning(): void
    {
        $payload = $this->serverPayload([
            'status' => 'BUILD',

            /*
             * ArvanCloud may temporarily expose incomplete shapes
             * while the server is still being built.
             */
            'addresses' => null,

            'networks' => [
                'temporary' => true,
            ],

            'security_groups' => null,
        ]);

        $server = $this->mapper->mapServer(
            payload: $payload,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'ubuntu',
        );

        $this->assertSame(
            CloudServerStatus::Provisioning,
            $server->status,
        );

        $this->assertSame(
            [],
            $server->addresses,
        );

        $this->assertSame(
            [],
            $server->networkIds,
        );

        $this->assertSame(
            [],
            $server->securityGroupIds,
        );
    }

    public function test_it_tolerates_missing_provider_fields_while_server_is_provisioning(): void
    {
        $payload = $this->serverPayload([
            'status' => 'CREATING',
        ]);

        unset(
            $payload['data'][0]['addresses'],
            $payload['data'][0]['networks'],
            $payload['data'][0]['security_groups'],
        );

        $server = $this->mapper->mapServer(
            payload: $payload,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'ubuntu',
        );

        $this->assertSame(
            CloudServerStatus::Provisioning,
            $server->status,
        );

        $this->assertSame(
            [],
            $server->addresses,
        );

        $this->assertSame(
            [],
            $server->networkIds,
        );

        $this->assertSame(
            [],
            $server->securityGroupIds,
        );
    }

    public function test_it_keeps_active_server_network_validation_strict(): void
    {
        $payload = $this->serverPayload([
            'status' => 'ACTIVE',
            'networks' => [
                'temporary' => true,
            ],
        ]);

        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud server networks must be a list.',
        );

        $this->mapper->mapServer(
            payload: $payload,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'ubuntu',
        );
    }

    public function test_it_keeps_active_server_address_validation_strict(): void
    {
        $payload = $this->serverPayload([
            'status' => 'ACTIVE',
            'addresses' => null,
        ]);

        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud server field [addresses] must be an array.',
        );

        $this->mapper->mapServer(
            payload: $payload,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'ubuntu',
        );
    }

    public function test_it_keeps_active_server_security_group_validation_strict(): void
    {
        $payload = $this->serverPayload([
            'status' => 'ACTIVE',
            'security_groups' => null,
        ]);

        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud server security groups must be a list.',
        );

        $this->mapper->mapServer(
            payload: $payload,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'ubuntu',
        );
    }

    public function test_it_uses_default_username_when_provider_username_is_missing(): void
    {
        $payload = $this->serverPayload();

        unset(
            $payload['data'][0]['username'],
        );

        $server = $this->mapper->mapServer(
            payload: $payload,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'ubuntu',
        );

        $this->assertSame(
            'ubuntu',
            $server->username,
        );
    }

    public function test_it_throws_when_requested_server_is_not_found(): void
    {
        $this->expectException(
            CloudResourceNotFoundException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server [missing-server-id] was not found.',
        );

        $this->mapper->mapServer(
            payload: $this->serverPayload(),
            regionId: self::REGION_ID,
            serverId: 'missing-server-id',
            defaultUsername: 'ubuntu',
        );
    }

    public function test_it_rejects_missing_data_envelope(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud regions response has an invalid data envelope.',
        );

        $this->mapper->mapRegions([
            'regions' => [],
        ]);
    }

    public function test_it_rejects_missing_required_image_field(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->mapper->mapImages(
            [
                'data' => [
                    [
                        'name' => 'ubuntu',
                        'display' => true,
                        'images' => [
                            [
                                'name' => '24.04',
                                'distribution_name' => 'ubuntu',
                                'disk' => 0,
                                'ram' => 0,
                                'ssh_key' => true,
                                'ssh_password' => true,
                            ],
                        ],
                    ],
                ],
            ],
            self::REGION_ID,
        );
    }

    public function test_it_rejects_unsupported_ip_version(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud returned unsupported IP version [5].',
        );

        $this->mapper->mapNetworks(
            [
                'data' => [
                    [
                        'id' => 'network-id',
                        'name' => 'network',
                        'status' => 'ACTIVE',
                        'subnets' => [
                            [
                                'ip_version' => '5',
                                'cidr' => '192.0.2.0/24',
                                'gateway_ip' => '192.0.2.1',
                                'enable_dhcp' => true,
                            ],
                        ],
                    ],
                ],
            ],
            self::REGION_ID,
        );
    }

    public function test_ssh_key_mapping_remains_blocked_until_schema_is_verified(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud SSH key response schema has not been verified.',
        );

        $this->mapper->mapSshKeys(
            ['data' => []],
            self::REGION_ID,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{data: list<array<string, mixed>>}
     */
    private function serverPayload(
        array $overrides = [],
    ): array {
        $server = [
            'id' => self::SERVER_ID,
            'name' => 'xdeploy-server',
            'status' => 'ACTIVE',
            'username' => 'ubuntu',

            'flavor' => [
                'id' => 'eco-2-1-0',
                'name' => 'eco-small2',
            ],

            'image' => [
                'id' => '3236878e-2bdc-4cdd-b082-61b3eeb3f9df',
            ],

            'created' => '2026-08-04T18:14:54+00:00',

            'addresses' => [
                'Default network' => [
                    [
                        'addr' => '185.204.169.89',
                        'version' => 4,
                        'is_public' => true,
                        'is_vpc' => false,
                        'type' => 'fixed',
                    ],
                ],
            ],

            /*
             * The real ArvanCloud schema returns networks as UUID strings.
             */
            'networks' => [
                'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',
            ],

            /*
             * Security groups are returned as objects containing an ID.
             */
            'security_groups' => [
                [
                    'id' => '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                ],
            ],

            'volume_backed' => true,
            'ha_enabled' => false,
        ];

        return [
            'data' => [
                array_replace(
                    $server,
                    $overrides,
                ),
            ],
        ];
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function fixture(string $name): array
    {
        $path = base_path(
            "tests/Fixtures/Cloud/ArvanCloud/{$name}",
        );

        $contents = file_get_contents($path);

        $this->assertNotFalse(
            $contents,
            "Unable to read fixture [{$name}].",
        );

        $payload = json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray(
            $payload,
        );

        return $payload;
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @return T
     */
    private function findById(
        array $items,
        string $id,
    ): object {
        foreach ($items as $item) {
            if (
                property_exists($item, 'id')
                && $item->id === $id
            ) {
                return $item;
            }
        }

        $this->fail(
            "Unable to find mapped resource [{$id}].",
        );
    }
}
