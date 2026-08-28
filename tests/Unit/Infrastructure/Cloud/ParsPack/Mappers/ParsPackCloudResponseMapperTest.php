<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ParsPack\Mappers;

use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Infrastructure\Cloud\ParsPack\Mappers\ParsPackCloudResponseMapper;
use PHPUnit\Framework\TestCase;

final class ParsPackCloudResponseMapperTest extends TestCase
{
    public function test_it_maps_regions_and_uses_slug_as_identity(): void
    {
        $regions = $this->mapper()->mapRegions([
            'regions' => [
                [
                    'name' => 'tehran',
                    'slug' => 'tehran11',
                    'available' => true,
                    'sizes' => ['irVPS2'],
                ],
                [
                    'name' => 'frankfurt',
                    'slug' => 'frankfurt',
                    'available' => true,
                    'sizes' => ['deVPS2'],
                ],
            ],
            'links' => [],
            'meta' => ['total' => 2],
        ]);

        $this->assertSame('tehran11', $regions[0]->id);
        $this->assertSame('IR', $regions[0]->country);
        $this->assertSame('frankfurt', $regions[1]->id);
        $this->assertSame('DE', $regions[1]->country);
    }

    public function test_it_normalizes_toman_size_pricing_to_irr(): void
    {
        $sizes = $this->mapper()->mapSizes([
            'sizes' => [
                [
                    'slug' => 'deVPS2',
                    'memory' => 2048,
                    'vcpus' => 1,
                    'disk' => 40,
                    'transfer' => 1,
                    'transfer_type' => 'total_traffic',
                    'price_monthly' => 1246920,
                    'price_hourly' => 1731.25,
                    'regions' => ['frankfurt'],
                    'description' => '',
                    'id' => 997,
                    'available' => true,
                ],
                [
                    'slug' => 'irVPS2',
                    'memory' => 2048,
                    'vcpus' => 1,
                    'disk' => 40,
                    'transfer' => 1,
                    'transfer_type' => 'download_only',
                    'price_monthly' => 1000000,
                    'price_hourly' => 1500,
                    'regions' => ['tehran11'],
                    'description' => '',
                    'id' => 998,
                    'available' => true,
                ],
            ],
            'links' => [],
            'meta' => ['total' => 2],
        ], 'frankfurt');

        $this->assertCount(1, $sizes);
        $this->assertSame('deVPS2', $sizes[0]->id);
        $this->assertSame(2048, $sizes[0]->memoryMiB);
        $this->assertSame(40, $sizes[0]->diskGiB);
        $this->assertSame('17312.5', $sizes[0]->hourlyPrice?->amount);
        $this->assertSame('12469200', $sizes[0]->monthlyPrice?->amount);
        $this->assertSame('IRR', $sizes[0]->monthlyPrice?->currencyCode);
    }

    public function test_it_filters_images_locally_and_skips_unsupported_base_images(): void
    {
        $images = $this->mapper()->mapImages([
            'images' => [
                [
                    'id' => 76,
                    'slug' => 'ubuntu24-cloudinit-qcow2',
                    'name' => 'Ubuntu24-x64',
                    'type' => 'base',
                    'distribution' => 'ubuntu',
                    'status' => 'available',
                    'public' => true,
                    'regions' => [],
                    'min_disk_size' => 0,
                ],
                [
                    'id' => 200,
                    'slug' => 'windows-2022',
                    'name' => 'Windows Server 2022 x64',
                    'type' => 'base',
                    'status' => 'available',
                    'public' => true,
                    'regions' => [],
                ],
                [
                    'id' => 300,
                    'slug' => 'nextcloud-ubuntu24',
                    'name' => 'NextCloud Ubuntu24',
                    'type' => 'application',
                    'status' => 'available',
                    'public' => true,
                    'regions' => [],
                ],
            ],
            'links' => [],
            'meta' => ['total' => 3],
        ], 'frankfurt');

        $this->assertCount(1, $images);
        $this->assertSame('ubuntu24-cloudinit-qcow2', $images[0]->id);
        $this->assertSame('ubuntu', $images[0]->distribution);
        $this->assertSame('24.04', $images[0]->version);
        $this->assertSame('x86_64', $images[0]->architecture);
        $this->assertTrue($images[0]->supportsSshKey);
        $this->assertTrue($images[0]->supportsPassword);
    }

    public function test_it_maps_wrapped_create_response(): void
    {
        $created = $this->mapper()->mapCreatedServer([
            'vm' => [
                'id' => 'a34a-4c03-5f73-1a14',
                'name' => 'coreflare-api-test',
                'status' => 'new',
                'created_at' => '2026-08-28T08:26:51.000000Z',
                'region' => [
                    'name' => 'frankfurt',
                    'slug' => 'frankfurt',
                ],
            ],
        ]);

        $this->assertSame('a34a-4c03-5f73-1a14', $created->id);
        $this->assertSame('frankfurt', $created->regionId);
        $this->assertSame(CloudServerStatus::Provisioning, $created->status);
        $this->assertSame('root', $created->username);
        $this->assertFalse($created->hasGeneratedPassword());
    }

    public function test_it_maps_actual_wrapped_vm_readiness_and_network_shape(): void
    {
        $server = $this->mapper()->mapServer([
            'vm' => [
                'id' => 'a34a-4c03-5f73-1a14',
                'name' => 'coreflare-api-test',
                'status' => 'active',
                'memory' => 2048,
                'vcpus' => 1,
                'disk' => 40,
                'created_at' => '2026-08-28T08:26:51.000000Z',
                'image' => [
                    'slug' => 'ubuntu24-cloudinit-qcow2',
                ],
                'size' => [
                    'slug' => 'deVPS2',
                    'memory' => 2048,
                    'vcpus' => 1,
                    'disk' => 40,
                ],
                'size_slug' => 'deVPS2',
                'region' => [
                    'name' => 'frankfurt',
                    'slug' => 'frankfurt',
                ],
                'networks' => [
                    'v4' => [
                        [
                            'ip_address' => '185.110.191.31',
                            'netmask' => '255.255.255.0',
                            'gateway' => '185.110.191.254',
                            'type' => 'public',
                        ],
                        [
                            'ip_address' => '172.16.0.2',
                            'netmask' => '255.255.0.0',
                            'gateway' => '',
                            'type' => 'private',
                        ],
                    ],
                    'v6' => [],
                ],
                'vpc_uuid' => '7892',
                'action' => null,
            ],
        ], 'frankfurt');

        $this->assertSame(CloudServerStatus::Active, $server->status);
        $this->assertSame(CloudServerPowerState::Running, $server->powerState);
        $this->assertSame('185.110.191.31', $server->firstPublicIpv4());
        $this->assertSame(['7892'], $server->networkIds);
        $this->assertSame('root', $server->username);
        $this->assertSame('deVPS2', $server->sizeId);
    }

    public function test_off_vm_remains_active_resource_with_stopped_power_state(): void
    {
        $server = $this->mapper()->mapServer([
            'vm' => [
                'id' => 'a34a-4c03-5f73-1a14',
                'name' => 'coreflare-api-test',
                'status' => 'off',
                'region' => ['slug' => 'frankfurt'],
                'size' => ['slug' => 'deVPS2'],
                'image' => ['slug' => 'ubuntu24-cloudinit-qcow2'],
                'networks' => [],
                'vpc_uuid' => '7892',
            ],
        ], 'frankfurt');

        $this->assertSame(CloudServerStatus::Active, $server->status);
        $this->assertSame(CloudServerPowerState::Stopped, $server->powerState);
    }

    public function test_empty_vm_inventory_uses_real_plural_collection_shape(): void
    {
        $servers = $this->mapper()->mapServerInventory([
            'vms' => [],
            'links' => [],
            'meta' => ['total' => 0],
        ]);

        $this->assertSame([], $servers);
    }

    private function mapper(): ParsPackCloudResponseMapper
    {
        return new ParsPackCloudResponseMapper();
    }
}
