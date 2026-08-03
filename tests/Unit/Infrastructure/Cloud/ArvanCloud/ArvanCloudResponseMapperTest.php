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
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use JsonException;
use Tests\TestCase;

final class ArvanCloudResponseMapperTest extends TestCase
{
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
            'eu-west1-a',
        );

        $this->assertInstanceOf(
            CloudRegionData::class,
            $region,
        );

        $this->assertSame(
            'Germany / Karlsruhe / Goethe',
            $region->displayName,
        );

        $this->assertSame('Germany', $region->country);
        $this->assertSame('Karlsruhe', $region->city);
        $this->assertSame('Goethe', $region->dataCenter);
        $this->assertTrue($region->canCreateServers);
        $this->assertTrue($region->isVisible);
        $this->assertTrue($region->supportsVolumeBacked);
    }

    public function test_it_maps_sizes_and_exact_prices(): void
    {
        $sizes = $this->mapper->mapSizes(
            $this->fixture('sizes.json'),
            'eu-west1-a',
        );

        $size = $this->findById(
            $sizes,
            'eco-2-2-0',
        );

        $this->assertInstanceOf(
            CloudSizeData::class,
            $size,
        );

        $this->assertSame('eco-small4', $size->name);
        $this->assertSame('eu-west1-a', $size->regionId);
        $this->assertSame(2, $size->vCpu);
        $this->assertSame(2048, $size->memoryMiB);
        $this->assertSame(30, $size->diskGiB);
        $this->assertSame('economic', $size->category);

        $this->assertNotNull($size->hourlyPrice);
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

        $this->assertNotNull($size->monthlyPrice);
        $this->assertSame(
            '16704000',
            $size->monthlyPrice->amount,
        );
    }

    public function test_it_flattens_and_maps_images(): void
    {
        $images = $this->mapper->mapImages(
            $this->fixture('images.json'),
            'eu-west1-a',
        );

        $image = $this->findById(
            $images,
            '00aaa9d1-3e0a-468c-aaf4-334513981e42',
        );

        $this->assertInstanceOf(
            CloudImageData::class,
            $image,
        );

        $this->assertSame('Ubuntu 24.04', $image->name);
        $this->assertSame('Ubuntu', $image->distribution);
        $this->assertSame('24.04', $image->version);
        $this->assertNull($image->minDiskGiB);
        $this->assertNull($image->minMemoryMiB);
        $this->assertTrue($image->supportsSshKey);
        $this->assertTrue($image->supportsPassword);
    }

    public function test_it_maps_network_from_primary_subnet(): void
    {
        $networks = $this->mapper->mapNetworks(
            $this->fixture('networks.json'),
            'eu-west1-a',
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

        $this->assertTrue($network->isActive);
        $this->assertTrue($network->dhcpEnabled);
    }

    public function test_it_maps_security_groups_without_provider_fields(): void
    {
        $groups = $this->mapper->mapSecurityGroups(
            $this->fixture('security-groups.json'),
            'eu-west1-a',
        );

        $group = $this->findById(
            $groups,
            '8449a4f5-5709-4017-9e63-45496bfe5cc9',
        );

        $this->assertInstanceOf(
            CloudSecurityGroupData::class,
            $group,
        );

        $this->assertSame('default', $group->name);
        $this->assertTrue($group->isDefault);
        $this->assertTrue($group->isReadOnly);

        $this->assertFalse(
            property_exists($group, 'realName'),
        );

        $this->assertFalse(
            property_exists($group, 'rules'),
        );
    }

    public function test_it_maps_quota_limits_and_usage(): void
    {
        $quota = $this->mapper->mapQuota(
            $this->fixture('quota.json'),
            'eu-west1-a',
        );

        $this->assertSame(
            'eu-west1-a',
            $quota->regionId,
        );

        $this->assertSame(1000, $quota->instancesLimit);
        $this->assertSame(0, $quota->instancesUsed);
        $this->assertSame(8000, $quota->vCpuLimit);
        $this->assertSame(0, $quota->vCpuUsed);
        $this->assertSame(
            13_107_200,
            $quota->memoryMiBLimit,
        );
        $this->assertSame(0, $quota->memoryMiBUsed);
        $this->assertSame(1600, $quota->sshKeysLimit);
        $this->assertNull($quota->sshKeysUsed);
    }

    public function test_it_rejects_missing_data_envelope(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
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

        $this->mapper->mapImages([
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
        ], 'eu-west1-a');
    }

    public function test_it_rejects_unsupported_ip_version(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->mapper->mapNetworks([
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
        ], 'eu-west1-a');
    }

    public function test_ssh_key_mapping_remains_blocked_until_schema_is_verified(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->mapper->mapSshKeys(
            ['data' => []],
            'eu-west1-a',
        );
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

        $this->assertIsArray($payload);

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
