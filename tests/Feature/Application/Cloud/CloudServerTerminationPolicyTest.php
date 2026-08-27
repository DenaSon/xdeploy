<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Servers\Termination\CloudServerTerminationPolicyResolver;
use App\Application\Cloud\Servers\Termination\CloudServerTerminationState;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class CloudServerTerminationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_arvan_uses_immediate_delete_state(): void
    {
        $server = $this->cloudServer(
            provider: CloudProviderType::Arvan,
            expiresAt: now()->subMinute(),
        );

        $decision = (new CloudServerTerminationPolicyResolver)
            ->advance($server);

        $this->assertSame(
            CloudServerTerminationState::ReadyForDelete,
            $decision->state,
        );
        $this->assertTrue($decision->readyForDelete());
    }

    public function test_running_liara_advances_to_power_off_requested(): void
    {
        $server = $this->cloudServer(
            provider: CloudProviderType::Liara,
            expiresAt: now()->subMinutes(10),
        );
        $lifecycle = $this->mockLifecycle();
        $lifecycle->expects($this->once())
            ->method('powerOff')
            ->with('iran', 'liara-server-123');

        $decision = $this->resolver(
            providerServer: $this->providerServer(
                powerState: CloudServerPowerState::Running,
                createdHoursAgo: 25,
            ),
            lifecycle: $lifecycle,
        )->advance($server);

        $this->assertSame(
            CloudServerTerminationState::PowerOffRequested,
            $decision->state,
        );
        $this->assertFalse($decision->readyForDelete());
    }

    public function test_liara_waiting_states_are_explicit(): void
    {
        $lifecycle = $this->mockLifecycle();
        $lifecycle->expects($this->never())->method('powerOff');

        $unknownServer = $this->cloudServer(
            provider: CloudProviderType::Liara,
            expiresAt: now()->subMinutes(10),
            providerServerId: 'liara-server-unknown',
        );
        $unknown = $this->resolver(
            providerServer: $this->providerServer(
                powerState: CloudServerPowerState::Unknown,
                createdHoursAgo: 25,
            ),
            lifecycle: $lifecycle,
        )->advance($unknownServer);

        $this->assertSame(
            CloudServerTerminationState::WaitingForPowerState,
            $unknown->state,
        );

        $graceServer = $this->cloudServer(
            provider: CloudProviderType::Liara,
            expiresAt: now()->subMinutes(2),
            providerServerId: 'liara-server-grace',
        );
        $grace = $this->resolver(
            providerServer: $this->providerServer(
                powerState: CloudServerPowerState::Stopped,
                createdHoursAgo: 25,
            ),
            lifecycle: $lifecycle,
        )->advance($graceServer);

        $this->assertSame(
            CloudServerTerminationState::WaitingForExpirationGrace,
            $grace->state,
        );

        $youngServer = $this->cloudServer(
            provider: CloudProviderType::Liara,
            expiresAt: now()->subMinutes(10),
            providerServerId: 'liara-server-young',
        );
        $young = $this->resolver(
            providerServer: $this->providerServer(
                powerState: CloudServerPowerState::Stopped,
                createdHoursAgo: 23,
            ),
            lifecycle: $lifecycle,
        )->advance($youngServer);

        $this->assertSame(
            CloudServerTerminationState::WaitingForProviderMinimumAge,
            $young->state,
        );
    }

    public function test_stopped_mature_liara_server_reaches_ready_for_delete(): void
    {
        $server = $this->cloudServer(
            provider: CloudProviderType::Liara,
            expiresAt: now()->subMinutes(10),
        );
        $lifecycle = $this->mockLifecycle();
        $lifecycle->expects($this->never())->method('powerOff');

        $decision = $this->resolver(
            providerServer: $this->providerServer(
                powerState: CloudServerPowerState::Stopped,
                createdHoursAgo: 25,
            ),
            lifecycle: $lifecycle,
        )->advance($server);

        $this->assertSame(
            CloudServerTerminationState::ReadyForDelete,
            $decision->state,
        );
        $this->assertTrue($decision->readyForDelete());
    }

    private function resolver(
        CloudServerData $providerServer,
        CloudServerLifecycleInterface $lifecycle,
    ): CloudServerTerminationPolicyResolver {
        $provider = new TerminationPolicyTestProvider(
            $providerServer,
        );

        $registry = $this->createMock(
            CloudProviderRegistryInterface::class,
        );
        $registry->method('resolve')
            ->with(CloudProviderType::Liara)
            ->willReturn($provider);
        $registry->method('resolveCapability')
            ->with(
                CloudProviderType::Liara,
                CloudServerLifecycleInterface::class,
            )
            ->willReturn($lifecycle);

        return new CloudServerTerminationPolicyResolver(
            providers: $registry,
        );
    }

    private function mockLifecycle(): CloudServerLifecycleInterface&MockObject
    {
        return $this->createMock(
            CloudServerLifecycleInterface::class,
        );
    }

    private function providerServer(
        CloudServerPowerState $powerState,
        int $createdHoursAgo,
    ): CloudServerData {
        return new CloudServerData(
            id: 'liara-server-123',
            name: 'Liara Expiring Server',
            regionId: 'iran',
            status: CloudServerStatus::Active,
            username: 'root',
            sizeId: 'standard-base-g2',
            imageId: 'ubuntu-24.04',
            createdAt: now()->subHours($createdHoursAgo)->toDateTimeImmutable(),
            powerState: $powerState,
        );
    }

    private function cloudServer(
        CloudProviderType $provider,
        mixed $expiresAt,
        string $providerServerId = 'liara-server-123',
    ): Server {
        $user = User::factory()->create();
        $isLiara = $provider === CloudProviderType::Liara;

        return $user->servers()->create([
            'name' => 'Expiring Cloud Server',
            'host' => '203.0.113.20',
            'port' => 22,
            'username' => $isLiara ? 'root' : 'ubuntu',
            'status' => ServerStatus::Active,
            'cloud_provider' => $provider,
            'cloud_server_id' => $isLiara
                ? $providerServerId
                : 'cloud-server-123',
            'cloud_region' => $isLiara ? 'iran' : 'eu-west1-a',
            'provisioned_at' => now()->subDay(),
            'expires_at' => $expiresAt,
        ]);
    }
}

final readonly class TerminationPolicyTestProvider implements CloudProviderInterface
{
    public function __construct(
        private CloudServerData $server,
    ) {}

    public function listRegions(): array
    {
        return [];
    }

    public function listSizes(string $region): array
    {
        return [];
    }

    public function listImages(string $region): array
    {
        return [];
    }

    public function findServer(
        string $region,
        string $serverId,
    ): CloudServerData {
        return $this->server;
    }
}
