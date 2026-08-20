<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Servers;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Application\Cloud\Servers\RebootCloudServerAction;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class RebootCloudServerActionTest extends TestCase
{
    public function test_it_routes_reboot_to_the_servers_owning_provider(): void
    {
        $lifecycle = $this->createMock(
            CloudServerLifecycleInterface::class,
        );

        $lifecycle
            ->expects($this->once())
            ->method('reboot')
            ->with(
                'iran',
                'liara-vm-123',
            );

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

        $server = new Server;
        $server->forceFill([
            'cloud_provider' => 'liara',
            'cloud_region' => 'iran',
            'cloud_server_id' => 'liara-vm-123',
        ]);

        (new RebootCloudServerAction(
            capabilities: new CloudServerCapabilityResolver(
                providers: $providers,
            ),
        ))->handle($server);
    }
}
