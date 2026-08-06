<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cloud\DTOs;

use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CloudServerDataTest extends TestCase
{
    public function test_it_preserves_backward_compatible_defaults(): void
    {
        $server = new CloudServerData(
            id: 'server-123',
            name: 'xdeploy-server',
            regionId: 'eu-west1-a',
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            sizeId: 'eco-1-1-0',
            imageId: 'ubuntu-image-id',
            createdAt: null,
        );

        $this->assertNull(
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

        $this->assertNull(
            $server->taskState,
        );

        $this->assertNull(
            $server->providerError,
        );

        $this->assertSame(
            CloudServerPowerState::Unknown,
            $server->powerState,
        );
    }

    public function test_it_returns_unique_public_ipv4_addresses(): void
    {
        $server = $this->server(
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),

                $this->publicIpv4(
                    '185.204.168.213',
                ),

                $this->publicIpv4(
                    '130.185.122.73',
                ),

                $this->privateIpv4(
                    '10.0.0.5',
                ),

                $this->publicIpv6(
                    '2001:db8::1',
                ),
            ],
        );

        $this->assertSame(
            [
                '185.204.168.213',
                '130.185.122.73',
            ],
            $server->publicIpv4s(),
        );
    }

    public function test_it_returns_first_public_ipv4(): void
    {
        $server = $this->server(
            addresses: [
                $this->privateIpv4(
                    '10.0.0.5',
                ),

                $this->publicIpv4(
                    '185.204.168.213',
                ),

                $this->publicIpv4(
                    '130.185.122.73',
                ),
            ],
        );

        $this->assertSame(
            '185.204.168.213',
            $server->firstPublicIpv4(),
        );

        $this->assertTrue(
            $server->hasPublicIpv4(),
        );
    }

    public function test_it_returns_null_when_public_ipv4_is_missing(): void
    {
        $server = $this->server(
            addresses: [
                $this->privateIpv4(
                    '10.0.0.5',
                ),

                $this->publicIpv6(
                    '2001:db8::1',
                ),
            ],
        );

        $this->assertSame(
            [],
            $server->publicIpv4s(),
        );

        $this->assertNull(
            $server->firstPublicIpv4(),
        );

        $this->assertFalse(
            $server->hasPublicIpv4(),
        );
    }

    public function test_it_exposes_runtime_power_state_helpers(): void
    {
        $running = $this->server(
            powerState: CloudServerPowerState::Running,
        );

        $stopped = $this->server(
            powerState: CloudServerPowerState::Stopped,
        );

        $transitioning = $this->server(
            powerState: CloudServerPowerState::Transitioning,
        );

        $this->assertTrue(
            $running->isRunning(),
        );

        $this->assertFalse(
            $running->isStopped(),
        );

        $this->assertTrue(
            $stopped->isStopped(),
        );

        $this->assertFalse(
            $stopped->isRunning(),
        );

        $this->assertTrue(
            $transitioning->isTransitioning(),
        );
    }

    public function test_it_detects_provider_error(): void
    {
        $server = $this->server(
            providerError: 'Resize operation failed.',
        );

        $this->assertTrue(
            $server->hasProviderError(),
        );
    }

    public function test_it_ignores_empty_provider_error(): void
    {
        $withoutError = $this->server(
            providerError: null,
        );

        $withWhitespace = $this->server(
            providerError: '   ',
        );

        $this->assertFalse(
            $withoutError->hasProviderError(),
        );

        $this->assertFalse(
            $withWhitespace->hasProviderError(),
        );
    }

    public function test_it_detects_size_information(): void
    {
        $server = $this->server(
            sizeId: 'eco-2-2-0',
        );

        $this->assertTrue(
            $server->hasSizeInformation(),
        );
    }

    public function test_it_rejects_empty_size_information(): void
    {
        $withoutSize = $this->server(
            sizeId: null,
        );

        $withWhitespace = $this->server(
            sizeId: '   ',
        );

        $this->assertFalse(
            $withoutSize->hasSizeInformation(),
        );

        $this->assertFalse(
            $withWhitespace->hasSizeInformation(),
        );
    }

    public function test_it_detects_complete_resource_information(): void
    {
        $server = $this->server(
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: 50,
        );

        $this->assertTrue(
            $server->hasResourceInformation(),
        );
    }

    public function test_it_rejects_incomplete_resource_information(): void
    {
        $missingCpu = $this->server(
            vCpu: null,
            memoryMiB: 2048,
            diskGiB: 50,
        );

        $missingMemory = $this->server(
            vCpu: 2,
            memoryMiB: null,
            diskGiB: 50,
        );

        $missingDisk = $this->server(
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: null,
        );

        $this->assertFalse(
            $missingCpu->hasResourceInformation(),
        );

        $this->assertFalse(
            $missingMemory->hasResourceInformation(),
        );

        $this->assertFalse(
            $missingDisk->hasResourceInformation(),
        );
    }

    public function test_running_active_server_is_ready_for_ssh_check(): void
    {
        $server = $this->server(
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),
            ],
            powerState: CloudServerPowerState::Running,
        );

        $this->assertTrue(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_unknown_power_state_remains_ready_for_backward_compatibility(): void
    {
        $server = $this->server(
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),
            ],
            powerState: CloudServerPowerState::Unknown,
        );

        $this->assertTrue(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_stopped_server_is_not_ready_for_ssh_check(): void
    {
        $server = $this->server(
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),
            ],
            powerState: CloudServerPowerState::Stopped,
        );

        $this->assertFalse(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_transitioning_server_is_not_ready_for_ssh_check(): void
    {
        $server = $this->server(
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),
            ],
            powerState: CloudServerPowerState::Transitioning,
        );

        $this->assertFalse(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_server_with_provider_error_is_not_ready_for_ssh_check(): void
    {
        $server = $this->server(
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),
            ],
            providerError: 'Provider operation failed.',
            powerState: CloudServerPowerState::Running,
        );

        $this->assertFalse(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_non_active_server_is_not_ready_for_ssh_check(): void
    {
        $server = $this->server(
            status: CloudServerStatus::Provisioning,
            username: 'ubuntu',
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),
            ],
            powerState: CloudServerPowerState::Running,
        );

        $this->assertFalse(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_server_without_public_ipv4_is_not_ready_for_ssh_check(): void
    {
        $server = $this->server(
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            addresses: [
                $this->privateIpv4(
                    '10.0.0.5',
                ),
            ],
            powerState: CloudServerPowerState::Running,
        );

        $this->assertFalse(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_server_without_username_is_not_ready_for_ssh_check(): void
    {
        $withoutUsername = $this->server(
            status: CloudServerStatus::Active,
            username: null,
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),
            ],
            powerState: CloudServerPowerState::Running,
        );

        $withWhitespaceUsername = $this->server(
            status: CloudServerStatus::Active,
            username: '   ',
            addresses: [
                $this->publicIpv4(
                    '185.204.168.213',
                ),
            ],
            powerState: CloudServerPowerState::Running,
        );

        $this->assertFalse(
            $withoutUsername->isReadyForSshCheck(),
        );

        $this->assertFalse(
            $withWhitespaceUsername->isReadyForSshCheck(),
        );
    }

    public function test_it_serializes_complete_server_data(): void
    {
        $createdAt = new DateTimeImmutable(
            '2026-08-06T10:00:00+00:00',
        );

        $publicAddress = $this->publicIpv4(
            '185.204.168.213',
        );

        $server = new CloudServerData(
            id: 'server-123',
            name: 'xdeploy-server',
            regionId: 'eu-west1-a',
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            sizeId: 'eco-2-2-0',
            imageId: 'ubuntu-image-id',
            createdAt: $createdAt,
            addresses: [
                $publicAddress,
            ],
            networkIds: [
                'network-123',
            ],
            securityGroupIds: [
                'security-group-123',
            ],
            volumeBacked: true,
            highAvailability: false,
            sizeName: 'eco-medium',
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: 50,
            taskState: 'completed',
            providerError: null,
            powerState: CloudServerPowerState::Running,
        );

        $this->assertSame(
            [
                'id' => 'server-123',
                'name' => 'xdeploy-server',
                'region_id' => 'eu-west1-a',
                'status' => CloudServerStatus::Active->value,
                'power_state' => CloudServerPowerState::Running->value,
                'username' => 'ubuntu',

                'size' => [
                    'id' => 'eco-2-2-0',
                    'name' => 'eco-medium',
                    'v_cpu' => 2,
                    'memory_mib' => 2048,
                    'disk_gib' => 50,
                ],

                'image_id' => 'ubuntu-image-id',
                'created_at' => $createdAt->format(
                    DATE_ATOM,
                ),

                'addresses' => [
                    $publicAddress->toArray(),
                ],

                'network_ids' => [
                    'network-123',
                ],

                'security_group_ids' => [
                    'security-group-123',
                ],

                'volume_backed' => true,
                'high_availability' => false,
                'task_state' => 'completed',
                'provider_error' => null,
            ],
            $server->toArray(),
        );
    }

    /**
     * @param  list<CloudServerAddressData>  $addresses
     */
    private function server(
        CloudServerStatus $status = CloudServerStatus::Active,
        ?string $username = 'ubuntu',
        ?string $sizeId = 'eco-1-1-0',
        array $addresses = [],
        ?string $sizeName = null,
        ?int $vCpu = null,
        ?int $memoryMiB = null,
        ?int $diskGiB = null,
        ?string $taskState = null,
        ?string $providerError = null,
        CloudServerPowerState $powerState =
        CloudServerPowerState::Unknown,
    ): CloudServerData {
        return new CloudServerData(
            id: 'server-123',
            name: 'xdeploy-server',
            regionId: 'eu-west1-a',
            status: $status,
            username: $username,
            sizeId: $sizeId,
            imageId: 'ubuntu-image-id',
            createdAt: null,
            addresses: $addresses,
            networkIds: [],
            securityGroupIds: [],
            volumeBacked: true,
            highAvailability: false,
            sizeName: $sizeName,
            vCpu: $vCpu,
            memoryMiB: $memoryMiB,
            diskGiB: $diskGiB,
            taskState: $taskState,
            providerError: $providerError,
            powerState: $powerState,
        );
    }

    private function publicIpv4(
        string $address,
    ): CloudServerAddressData {
        return new CloudServerAddressData(
            address: $address,
            version: CloudIpVersion::IPv4,
            isPublic: true,
            isVpc: false,
            type: 'fixed',
        );
    }

    private function privateIpv4(
        string $address,
    ): CloudServerAddressData {
        return new CloudServerAddressData(
            address: $address,
            version: CloudIpVersion::IPv4,
            isPublic: false,
            isVpc: true,
            type: 'fixed',
        );
    }

    private function publicIpv6(
        string $address,
    ): CloudServerAddressData {
        return new CloudServerAddressData(
            address: $address,
            version: CloudIpVersion::IPv6,
            isPublic: true,
            isVpc: false,
            type: 'fixed',
        );
    }
}
