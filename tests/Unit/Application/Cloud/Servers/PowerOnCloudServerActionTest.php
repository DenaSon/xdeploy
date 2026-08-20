<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Servers;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Application\Cloud\Servers\PowerOnCloudServerAction;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class PowerOnCloudServerActionTest extends TestCase
{
    public function test_it_routes_power_on_to_the_servers_owning_provider(): void
    {
        [$action, $lifecycle, $server] = $this->subject();

        $lifecycle
            ->expects($this->once())
            ->method('powerOn')
            ->with(
                'iran',
                'liara-vm-123',
            );

        $action->handle(
            $server,
        );
    }

    public function test_it_does_not_hide_provider_exceptions(): void
    {
        [$action, $lifecycle, $server] = $this->subject();

        $lifecycle
            ->expects($this->once())
            ->method('powerOn')
            ->willThrowException(
                new CloudConnectionException(
                    'Cloud provider is temporarily unavailable.',
                ),
            );

        $this->expectException(
            CloudConnectionException::class,
        );

        $action->handle(
            $server,
        );
    }

    /**
     * @return array{PowerOnCloudServerAction, CloudServerLifecycleInterface, Server}
     */
    private function subject(): array
    {
        $lifecycle = $this->createMock(
            CloudServerLifecycleInterface::class,
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

        return [
            new PowerOnCloudServerAction(
                capabilities: new CloudServerCapabilityResolver(
                    providers: $providers,
                ),
            ),
            $lifecycle,
            $server,
        ];
    }
}
