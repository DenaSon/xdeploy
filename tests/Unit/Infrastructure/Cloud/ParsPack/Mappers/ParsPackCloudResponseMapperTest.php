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
            'data' => [
                [
                    'name' => 'Tehran',
                    'slug' => 'tehran11',
                    'available' => true,
                ],
                [
                    'name' => 'Frankfurt',
                    'slug' => 'frankfurt',
                    'available' => true,
                ],
            ],
        ]);

        $this->assertSame('tehran11', $regions[0]->id);
        $this->assertSame('IR', $regions[0]->country);
        $this->assertSame('frankfurt', $regions[1]->id);
        $this->assertSame('DE', $regions[1]->country);
    }

    public function test_it_maps_fixed_size_pricing_as_raw_irr(): void
    {
        $sizes = $this->mapper()->mapSizes([
            'data' => [
                [
                    'slug' => 'deVPS2',
                    'memory' => 2048,
                    'vcpus' => 1,
                    'disk' => 40,
                    'price_monthly' => 1246920,
                    'price_hourly' => 1731.25,
                    'regions' => ['frankfurt'],
                    'description' => '',
                    'available' => true,
                ],
                [
                    'slug' => 'irVPS2',
                    'memory' => 2048,
                    'vcpus' => 1,
                    'disk' => 40,
                    'price_monthly' => 1000000,
                    'price_hourly' => 1500,
                    'regions' => ['tehran11'],
                    'description' => '',
                    'available' => true,
                ],
            ],
        ], 'frankfurt');

        $this->assertCount(1, $sizes);
        $this->assertSame('deVPS2', $sizes[0]->id);
        $this->assertSame(2048, $sizes[0]->memoryMiB);
        $this->assertSame(40, $sizes[0]->diskGiB);
        $this->assertSame('1731.25', $sizes[0]->hourlyPrice?->amount);
        $this->assertSame('1246920', $sizes[0]->monthlyPrice?->amount);
        $this->assertSame('IRR', $sizes[0]->monthlyPrice?->currencyCode);
    }

    public function test_it_filters_images_locally_and_skips_unsupported_base_images(): void
    {
        $images = $this->mapper()->mapImages([
            'data' => [
                [
                    'id' => 76,
                    'slug' => 'ubuntu24-cloudinit-qcow2',
                    'name' => 'Ubuntu 24 x64',
                    'type' => 'base',
                    'status' => 'available',
                    'public' => true,
                    'regions' => [],
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
                    'name' => 'NextCloud Ubuntu 24',
                    'type' => 'application',
                    'status' => 'available',
                    'public' => true,
                    'regions' => [],
                ],
            ],
        ], 'frankfurt');

        $this->assertCount(1, $images);
        $this->assertSame('ubuntu24-cloudinit-qcow2', $images[0]->id);
        $this->assertSame('ubuntu', $images[0]->distribution);
        $this->assertSame('24.04', $images[0]->version);
        $this->assertTrue($images[0]->supportsSshKey);
        $this->assertTrue($images[0]->supportsPassword);
    }

    public function test_it_maps_actual_vm_readiness_and_network_shape(): void
    {
        $server = $this->mapper()->mapServer([
            'id' => 'a34a-4c03-5f73-1a14',
            'name' => 'coreflare-api-test',
            'status' => 'active',
            'memory' => 2048,
            'vcpus' => 1,
            'disk' => 40,
            'image' => [
                'slug' => 'ubuntu24-cloudinit-qcow2',
            ],
            'size' => [
                'slug' => 'deVPS2',
                'memory' => 2048,
                'vcpus' => 1,
                'disk' => 40,
            ],
            'region' => [
                'slug' => 'frankfurt',
            ],
            'networks' => [
                [
                    'ip' => '185.110.191.31',
                    'type' => 'public',
                ],
                [
                    'ip' => '172.16.0.2',
                    'type' => 'private',
                ],
            ],
            'vpc_uuid' => '7892',
            'action' => null,
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
            'id' => 'a34a-4c03-5f73-1a14',
            'name' => 'coreflare-api-test',
            'status' => 'off',
            'region' => ['slug' => 'frankfurt'],
            'size' => ['slug' => 'deVPS2'],
            'image' => ['slug' => 'ubuntu24-cloudinit-qcow2'],
            'networks' => [],
            'vpc_uuid' => '7892',
        ], 'frankfurt');

        $this->assertSame(CloudServerStatus::Active, $server->status);
        $this->assertSame(CloudServerPowerState::Stopped, $server->powerState);
    }

    private function mapper(): ParsPackCloudResponseMapper
    {
        return new ParsPackCloudResponseMapper();
    }
}
