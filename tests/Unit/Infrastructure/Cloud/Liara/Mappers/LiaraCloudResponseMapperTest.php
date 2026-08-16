<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Liara\Mappers;

use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\Liara\Mappers\LiaraCloudResponseMapper;
use Tests\TestCase;

final class LiaraCloudResponseMapperTest extends TestCase
{
    public function test_it_maps_available_plans_and_normalizes_toman_prices_to_rials(): void
    {
        $sizes = $this->mapper()->mapSizes(
            response: [
                'plans' => [
                    'standard-base-g2' => $this->plan(
                        monthlyPrice: 1_050_000,
                        hourlyPrice: 1458.33,
                        cpu: 1,
                        ramGiB: 2,
                        volumeGiB: 20,
                    ),
                    'unavailable' => $this->plan(
                        monthlyPrice: 100,
                        hourlyPrice: 1,
                        cpu: 1,
                        ramGiB: 1,
                        volumeGiB: 10,
                        available: false,
                    ),
                    'another-region' => $this->plan(
                        monthlyPrice: 100,
                        hourlyPrice: 1,
                        cpu: 1,
                        ramGiB: 1,
                        volumeGiB: 10,
                        region: 'other',
                    ),
                ],
            ],
            region: 'iran',
        );

        $this->assertCount(1, $sizes);

        $size = $sizes[0];

        $this->assertSame('standard-base-g2', $size->id);
        $this->assertSame('iran', $size->regionId);
        $this->assertSame(1, $size->vCpu);
        $this->assertSame(2048, $size->memoryMiB);
        $this->assertSame(20, $size->diskGiB);
        $this->assertSame('14583', $size->hourlyPrice?->amount);
        $this->assertSame('10500000', $size->monthlyPrice?->amount);
        $this->assertSame('IRR', $size->hourlyPrice?->currencyCode);
    }

    public function test_it_derives_regions_from_plan_catalog(): void
    {
        $regions = $this->mapper()->mapRegions([
            'plans' => [
                'standard-base-g2' => $this->plan(
                    monthlyPrice: 1_050_000,
                    hourlyPrice: 1458.33,
                    cpu: 1,
                    ramGiB: 2,
                    volumeGiB: 20,
                ),
                'standard-plus-g2' => $this->plan(
                    monthlyPrice: 1_900_000,
                    hourlyPrice: 2638.88,
                    cpu: 2,
                    ramGiB: 4,
                    volumeGiB: 40,
                ),
            ],
        ]);

        $this->assertCount(1, $regions);
        $this->assertSame('iran', $regions[0]->id);
        $this->assertSame('IR', $regions[0]->country);
        $this->assertTrue($regions[0]->canCreateServers);
    }

    public function test_it_maps_operating_system_values_to_create_identifiers(): void
    {
        $images = $this->mapper()->mapImages(
            response: [
                'ubuntu' => [
                    '24.04',
                    '22.04',
                ],
                'docker' => [
                    '29.1.2',
                ],
            ],
            region: 'iran',
        );

        $this->assertSame(
            [
                'ubuntu-24.04',
                'ubuntu-22.04',
                'docker-29.1.2',
            ],
            array_map(
                static fn ($image): string => $image->id,
                $images,
            ),
        );

        $this->assertSame('ubuntu', $images[0]->distribution);
        $this->assertSame('24.04', $images[0]->version);
        $this->assertTrue($images[0]->supportsPassword);
    }

    public function test_it_maps_live_create_response_without_assuming_task_id_is_resource_id(): void
    {
        $created = $this->mapper()->mapCreatedServer(
            response: [
                'taskID' => '6a80c1906727f3d124d794cb',
                'VMID' => '6a80c18f6727f3d124d794c6',
            ],
            requestedName: 'cf-liara-test',
            region: 'iran',
        );

        $this->assertSame(
            '6a80c18f6727f3d124d794c6',
            $created->id,
        );
        $this->assertSame('cf-liara-test', $created->name);
        $this->assertSame('root', $created->username);
        $this->assertSame(
            CloudServerStatus::Provisioning,
            $created->status,
        );
        $this->assertFalse($created->hasGeneratedPassword());
    }

    public function test_it_requires_live_vm_id_in_create_response(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->mapper()->mapCreatedServer(
            response: [
                'taskID' => '6a80c1906727f3d124d794cb',
            ],
            requestedName: 'cf-liara-test',
            region: 'iran',
        );
    }

    public function test_it_maps_live_vm_details_and_async_root_password(): void
    {
        $server = $this->mapper()->mapServer(
            response: $this->vmDetails(
                power: 'POWERED_ON',
                guestState: 'RUNNING',
                rootPassword: 'generated-secret',
            ),
            region: 'iran',
        );

        $this->assertSame(
            '6a80c18f6727f3d124d794c6',
            $server->id,
        );
        $this->assertSame(CloudServerStatus::Active, $server->status);
        $this->assertSame(
            CloudServerPowerState::Running,
            $server->powerState,
        );
        $this->assertSame('46.34.163.219', $server->firstPublicIpv4());
        $this->assertSame('root', $server->username);
        $this->assertSame('standard-base-g2', $server->sizeId);
        $this->assertSame('ubuntu-24.04', $server->imageId);
        $this->assertSame(1, $server->vCpu);
        $this->assertSame(2048, $server->memoryMiB);
        $this->assertSame(20, $server->diskGiB);
        $this->assertSame('SUCCEEDED', $server->taskState);
        $this->assertSame(
            'generated-secret',
            $server->generatedPassword(),
        );
    }

    public function test_it_never_treats_masked_password_as_a_credential(): void
    {
        $server = $this->mapper()->mapServer(
            response: $this->vmDetails(
                power: 'POWERED_OFF',
                guestState: 'NOT_RUNNING',
                rootPassword: '*****',
            ),
            region: 'iran',
        );

        $this->assertSame(CloudServerStatus::Active, $server->status);
        $this->assertSame(
            CloudServerPowerState::Stopped,
            $server->powerState,
        );
        $this->assertFalse($server->hasGeneratedPassword());
    }

    public function test_it_maps_inventory_summary_without_fabricating_detail_fields(): void
    {
        $servers = $this->mapper()->mapServerInventory(
            response: [
                'vms' => [
                    [
                        '_id' => '6a80c18f6727f3d124d794c6',
                        'plan' => 'standard-base-g2',
                        'OS' => 'ubuntu-24.04',
                        'state' => 'CREATED',
                        'name' => 'cf-liara-test',
                        'createdAt' => '2026-08-15T19:44:15.993Z',
                        'power' => 'POWERED_OFF',
                    ],
                ],
            ],
            region: 'iran',
        );

        $this->assertCount(1, $servers);
        $this->assertSame(
            CloudServerPowerState::Stopped,
            $servers[0]->powerState,
        );
        $this->assertSame([], $servers[0]->addresses);
        $this->assertNull($servers[0]->generatedPassword());
    }

    public function test_it_maps_root_password_reset_response(): void
    {
        $result = $this->mapper()->mapRootPasswordReset([
            'password' => 'new-generated-password',
        ]);

        $this->assertSame(
            'new-generated-password',
            $result->password,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(
        int $monthlyPrice,
        float|int $hourlyPrice,
        int $cpu,
        int $ramGiB,
        int $volumeGiB,
        bool $available = true,
        string $region = 'iran',
    ): array {
        return [
            'available' => $available,
            'region' => $region,
            'monthlyPrice' => $monthlyPrice,
            'hourlyPrice' => $hourlyPrice,
            'volume' => $volumeGiB,
            'RAM' => [
                'amount' => $ramGiB,
            ],
            'CPU' => [
                'amount' => $cpu,
            ],
            'IPv4MonthlyPrice' => [
                200_000,
                350_000,
            ],
            'IPv4HourlyPrice' => [
                277.77,
                486.11,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vmDetails(
        string $power,
        string $guestState,
        string $rootPassword,
    ): array {
        return [
            '_id' => '6a80c18f6727f3d124d794c6',
            'plan' => 'standard-base-g2',
            'OS' => 'ubuntu-24.04',
            'state' => 'CREATED',
            'config' => [
                'SSHKeys' => [],
                'hostname' => 'ubuntu-cf-liara-test',
                'rootPassword' => $rootPassword,
            ],
            'name' => 'cf-liara-test',
            'guestCus' => [
                'status' => 'SUCCEEDED',
            ],
            'createdAt' => '2026-08-15T19:44:15.993Z',
            'IPs' => [
                [
                    'address' => '46.34.163.219',
                    'version' => 'v4',
                ],
            ],
            'power' => $power,
            'guestState' => $guestState,
            'planDetails' => [
                'available' => true,
                'region' => 'iran',
                'monthlyPrice' => 1_050_000,
                'hourlyPrice' => 1458.33,
                'volume' => 20,
                'RAM' => [
                    'amount' => 2,
                ],
                'CPU' => [
                    'amount' => 1,
                ],
            ],
        ];
    }

    private function mapper(): LiaraCloudResponseMapper
    {
        return new LiaraCloudResponseMapper();
    }
}
