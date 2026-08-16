<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Servers;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Application\Cloud\Servers\GetCloudServerActionsAction;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class GetCloudServerActionsActionTest extends TestCase
{
    public function test_it_reads_actions_from_the_servers_owning_provider(): void
    {
        $expected = [
            new CloudServerActionData(
                action: 'reboot',
                message: 'Reboot is available.',
                startedAt: null,
            ),
        ];

        $lifecycle = $this->createMock(
            CloudServerLifecycleInterface::class,
        );

        $lifecycle
            ->expects($this->once())
            ->method('getAvailableActions')
            ->with(
                'iran',
                'liara-vm-123',
            )
            ->willReturn($expected);

        $providers = $this->createMock(
            CloudProviderRegistryInterface::class,
        );

        $providers
            ->expects($this->once())
            ->method('resolveCapability')
            ->with(
                CloudProviderType::Liara,
                CloudServerLifecycleInterface::class,
            )
            ->willReturn($lifecycle);

        $server = new Server();
        $server->forceFill([
            'cloud_provider' => 'liara',
            'cloud_region' => 'iran',
            'cloud_server_id' => 'liara-vm-123',
        ]);

        $result = (new GetCloudServerActionsAction(
            capabilities: new CloudServerCapabilityResolver(
                providers: $providers,
            ),
        ))->handle($server);

        $this->assertSame(
            $expected,
            $result,
        );
    }
}
