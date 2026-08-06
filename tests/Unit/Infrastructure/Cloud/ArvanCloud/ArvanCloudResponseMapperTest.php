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
use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudResponseMapperTest extends TestCase
{
    private const string REGION_ID = 'eu-west1-a';

    private const string SERVER_ID = 'provider-server-id';

    private const string DEFAULT_USERNAME = 'ubuntu';

    private ArvanCloudResponseMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new ArvanCloudResponseMapper;
    }

    public function test_it_maps_regions(): void
    {
        $regions = $this->mapper->mapRegions(
            $this->fixture(
                'regions.json',
            ),
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
            defaultUsername: self::DEFAULT_USERNAME,
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
            self::DEFAULT_USERNAME,
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

    public function test_it_maps_direct_create_response(): void
    {
        $createdServer = $this->mapper->mapCreatedServer(
            payload: [
                'id' => self::SERVER_ID,
                'name' => 'provider-server-name',
                'username' => 'root',
                'password' => 'generated-password',
            ],
            regionId: self::REGION_ID,
            defaultUsername: self::DEFAULT_USERNAME,
            requestedName: 'requested-server-name',
        );

        $this->assertSame(
            self::SERVER_ID,
            $createdServer->id,
        );

        $this->assertSame(
            'provider-server-name',
            $createdServer->name,
        );

        $this->assertSame(
            'root',
            $createdServer->username,
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
            defaultUsername: self::DEFAULT_USERNAME,
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
            defaultUsername: self::DEFAULT_USERNAME,
            requestedName: '   ',
        );
    }

    public function test_it_maps_sizes_and_exact_prices(): void
    {
        $sizes = $this->mapper->mapSizes(
            $this->fixture(
                'sizes.json',
            ),
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
            $this->fixture(
                'images.json',
            ),
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
            $this->fixture(
                'networks.json',
            ),
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
            $this->fixture(
                'security-groups.json',
            ),
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
            $this->fixture(
                'quota.json',
            ),
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

    public function test_it_maps_direct_server_response(): void
    {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject(),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertCompleteServer(
            $server,
        );
    }

    public function test_it_maps_direct_server_inside_data_envelope(): void
    {
        $server = $this->mapper->mapServer(
            payload: [
                'data' => $this->serverObject(),
            ],
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertCompleteServer(
            $server,
        );
    }

    public function test_it_remains_compatible_with_server_list_response(): void
    {
        $server = $this->mapper->mapServer(
            payload: [
                'data' => [
                    $this->serverObject(),
                ],
            ],
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertCompleteServer(
            $server,
        );
    }

    public function test_it_maps_server_flavor_resources_and_operation_state(): void
    {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject([
                'task_state' => 'resize_complete',
                'error' => null,

                'flavor' => [
                    'id' => 'eco-2-2-0',
                    'name' => 'eco-small4',
                    'vcpus' => 2,
                    'ram' => 2048,
                    'disk' => 50,
                ],
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
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
            'resize_complete',
            $server->taskState,
        );

        $this->assertNull(
            $server->providerError,
        );
    }

    public function test_it_maps_provider_error(): void
    {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject([
                'status' => 'ERROR',
                'task_state' => 'resize_failed',
                'error' => 'Unable to resize server.',
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
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
    }

    #[DataProvider('serverStatusProvider')]
    public function test_it_maps_server_lifecycle_and_power_states(
        string $providerStatus,
        CloudServerStatus $expectedStatus,
        CloudServerPowerState $expectedPowerState,
    ): void {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject([
                'status' => $providerStatus,
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertSame(
            $expectedStatus,
            $server->status,
        );

        $this->assertSame(
            $expectedPowerState,
            $server->powerState,
        );
    }

    /**
     * @return array<string, array{
     *     string,
     *     CloudServerStatus,
     *     CloudServerPowerState
     * }>
     */
    public static function serverStatusProvider(): array
    {
        return [
            'active' => [
                'ACTIVE',
                CloudServerStatus::Active,
                CloudServerPowerState::Running,
            ],

            'shutoff' => [
                'SHUTOFF',
                CloudServerStatus::Active,
                CloudServerPowerState::Stopped,
            ],

            'stopped' => [
                'STOPPED',
                CloudServerStatus::Active,
                CloudServerPowerState::Stopped,
            ],

            'paused' => [
                'PAUSED',
                CloudServerStatus::Active,
                CloudServerPowerState::Stopped,
            ],

            'suspended' => [
                'SUSPENDED',
                CloudServerStatus::Active,
                CloudServerPowerState::Stopped,
            ],

            'powering on' => [
                'POWERING_ON',
                CloudServerStatus::Active,
                CloudServerPowerState::Transitioning,
            ],

            'powering off' => [
                'POWERING_OFF',
                CloudServerStatus::Active,
                CloudServerPowerState::Transitioning,
            ],

            'reboot' => [
                'REBOOT',
                CloudServerStatus::Active,
                CloudServerPowerState::Transitioning,
            ],

            'hard reboot' => [
                'HARD_REBOOT',
                CloudServerStatus::Active,
                CloudServerPowerState::Transitioning,
            ],

            'resize' => [
                'RESIZE',
                CloudServerStatus::Active,
                CloudServerPowerState::Transitioning,
            ],

            'verify resize' => [
                'VERIFY_RESIZE',
                CloudServerStatus::Active,
                CloudServerPowerState::Transitioning,
            ],

            'revert resize' => [
                'REVERT_RESIZE',
                CloudServerStatus::Active,
                CloudServerPowerState::Transitioning,
            ],

            'build' => [
                'BUILD',
                CloudServerStatus::Provisioning,
                CloudServerPowerState::Transitioning,
            ],

            'creating' => [
                'CREATING',
                CloudServerStatus::Provisioning,
                CloudServerPowerState::Transitioning,
            ],

            'queued' => [
                'QUEUED',
                CloudServerStatus::Provisioning,
                CloudServerPowerState::Transitioning,
            ],

            'error' => [
                'ERROR',
                CloudServerStatus::Failed,
                CloudServerPowerState::Error,
            ],

            'failed' => [
                'FAILED',
                CloudServerStatus::Failed,
                CloudServerPowerState::Error,
            ],

            'unknown' => [
                'UNEXPECTED_PROVIDER_STATUS',
                CloudServerStatus::Unknown,
                CloudServerPowerState::Unknown,
            ],
        ];
    }

    #[DataProvider('validFlavorResourceProvider')]
    public function test_it_normalizes_valid_flavor_resource_values(
        int|float|string $vCpu,
        int|float|string $memory,
        int|float|string $disk,
    ): void {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject([
                'flavor' => [
                    'id' => 'eco-2-2-0',
                    'name' => 'eco-small4',
                    'vcpus' => $vCpu,
                    'ram' => $memory,
                    'disk' => $disk,
                ],
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
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
    }

    /**
     * @return array<string, array{
     *     int|float|string,
     *     int|float|string,
     *     int|float|string
     * }>
     */
    public static function validFlavorResourceProvider(): array
    {
        return [
            'integers' => [
                2,
                2048,
                50,
            ],

            'whole floats' => [
                2.0,
                2048.0,
                50.0,
            ],

            'numeric strings' => [
                '2',
                '2048',
                '50',
            ],
        ];
    }

    #[DataProvider('invalidFlavorResourceProvider')]
    public function test_it_rejects_invalid_flavor_resource_values(
        string $field,
        mixed $value,
    ): void {
        $flavor = [
            'id' => 'eco-2-2-0',
            'name' => 'eco-small4',
            'vcpus' => 2,
            'ram' => 2048,
            'disk' => 50,
        ];

        $flavor[$field] = $value;

        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->mapper->mapServer(
            payload: $this->serverObject([
                'flavor' => $flavor,
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    /**
     * @return array<string, array{
     *     string,
     *     mixed
     * }>
     */
    public static function invalidFlavorResourceProvider(): array
    {
        return [
            'negative CPU' => [
                'vcpus',
                -1,
            ],

            'fractional CPU' => [
                'vcpus',
                1.5,
            ],

            'invalid RAM string' => [
                'ram',
                'two-gigabytes',
            ],

            'negative disk' => [
                'disk',
                -10,
            ],

            'boolean disk' => [
                'disk',
                true,
            ],

            'array disk' => [
                'disk',
                [
                    50,
                ],
            ],
        ];
    }

    public function test_it_allows_missing_optional_flavor_resources(): void
    {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject([
                'flavor' => [
                    'id' => 'eco-2-2-0',
                    'name' => 'eco-small4',
                ],
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertSame(
            'eco-2-2-0',
            $server->sizeId,
        );

        $this->assertSame(
            'eco-small4',
            $server->sizeName,
        );

        $this->assertNull(
            $server->vCpu,
        );

        $this->assertNull(
            $server->memoryMiB,
        );

        $this->assertNull(
            $server->diskGiB,
        );
    }

    public function test_it_uses_server_username_first(): void
    {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject([
                'username' => 'root',

                'image' => [
                    'id' => 'image-id',
                    'username' => 'ubuntu',
                ],
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'debian',
        );

        $this->assertSame(
            'root',
            $server->username,
        );
    }

    public function test_it_uses_image_username_when_server_username_is_missing(): void
    {
        $serverObject = $this->serverObject([
            'image' => [
                'id' => 'image-id',
                'username' => 'ubuntu',
            ],
        ]);

        unset(
            $serverObject['username'],
        );

        $server = $this->mapper->mapServer(
            payload: $serverObject,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'debian',
        );

        $this->assertSame(
            'ubuntu',
            $server->username,
        );
    }

    public function test_it_uses_default_username_when_provider_usernames_are_missing(): void
    {
        $serverObject = $this->serverObject([
            'image' => [
                'id' => 'image-id',
            ],
        ]);

        unset(
            $serverObject['username'],
        );

        $server = $this->mapper->mapServer(
            payload: $serverObject,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: 'debian',
        );

        $this->assertSame(
            'debian',
            $server->username,
        );
    }

    public function test_it_deduplicates_server_networks_and_security_groups(): void
    {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject([
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
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertSame(
            [
                'network-id',
            ],
            $server->networkIds,
        );

        $this->assertSame(
            [
                'security-group-id',
            ],
            $server->securityGroupIds,
        );
    }

    public function test_it_tolerates_incomplete_provider_fields_while_server_is_provisioning(): void
    {
        $server = $this->mapper->mapServer(
            payload: $this->serverObject([
                'status' => 'BUILD',
                'addresses' => null,
                'networks' => [
                    'temporary' => true,
                ],
                'security_groups' => null,
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );

        $this->assertSame(
            CloudServerStatus::Provisioning,
            $server->status,
        );

        $this->assertSame(
            CloudServerPowerState::Transitioning,
            $server->powerState,
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
        $serverObject = $this->serverObject([
            'status' => 'CREATING',
        ]);

        unset(
            $serverObject['addresses'],
            $serverObject['networks'],
            $serverObject['security_groups'],
        );

        $server = $this->mapper->mapServer(
            payload: $serverObject,
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
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
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud server networks must be a list.',
        );

        $this->mapper->mapServer(
            payload: $this->serverObject([
                'status' => 'ACTIVE',
                'networks' => [
                    'temporary' => true,
                ],
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    public function test_it_keeps_active_server_address_validation_strict(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud server field [addresses] must be an array.',
        );

        $this->mapper->mapServer(
            payload: $this->serverObject([
                'status' => 'ACTIVE',
                'addresses' => null,
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    public function test_it_keeps_active_server_security_group_validation_strict(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud server security groups must be a list.',
        );

        $this->mapper->mapServer(
            payload: $this->serverObject([
                'status' => 'ACTIVE',
                'security_groups' => null,
            ]),
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    public function test_it_rejects_mismatched_direct_server_identifier(): void
    {
        $this->expectException(
            CloudResourceNotFoundException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server [missing-server-id] was not found.',
        );

        $this->mapper->mapServer(
            payload: $this->serverObject(),
            regionId: self::REGION_ID,
            serverId: 'missing-server-id',
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    public function test_it_throws_when_requested_server_is_not_found_in_list(): void
    {
        $this->expectException(
            CloudResourceNotFoundException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server [missing-server-id] was not found.',
        );

        $this->mapper->mapServer(
            payload: [
                'data' => [
                    $this->serverObject(),
                ],
            ],
            regionId: self::REGION_ID,
            serverId: 'missing-server-id',
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    public function test_it_rejects_invalid_direct_server_envelope(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'ArvanCloud server response has an invalid data envelope.',
        );

        $this->mapper->mapServer(
            payload: [
                'data' => [
                    'name' => 'missing-id',
                ],
            ],
            regionId: self::REGION_ID,
            serverId: self::SERVER_ID,
            defaultUsername: self::DEFAULT_USERNAME,
        );
    }

    public function test_it_rejects_empty_server_identifier(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server identifier cannot be empty.',
        );

        $this->mapper->mapServer(
            payload: $this->serverObject(),
            regionId: self::REGION_ID,
            serverId: '   ',
            defaultUsername: self::DEFAULT_USERNAME,
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
            [
                'data' => [],
            ],
            self::REGION_ID,
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
            'name' => 'xdeploy-server',
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
                'id' => '3236878e-2bdc-4cdd-b082-61b3eeb3f9df',
                'username' => 'ubuntu',
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

            'networks' => [
                'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',
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

    private function assertCompleteServer(
        object $server,
    ): void {
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

        $this->assertNull(
            $server->taskState,
        );

        $this->assertNull(
            $server->providerError,
        );
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function fixture(
        string $name,
    ): array {
        $path = base_path(
            "tests/Fixtures/Cloud/ArvanCloud/{$name}",
        );

        $contents = file_get_contents(
            $path,
        );

        $this->assertNotFalse(
            $contents,
            "Unable to read fixture [{$name}].",
        );

        $payload = json_decode(
            json: $contents,
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
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
                property_exists(
                    $item,
                    'id',
                )
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
