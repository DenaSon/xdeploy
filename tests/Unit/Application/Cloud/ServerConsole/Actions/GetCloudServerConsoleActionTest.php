<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerConsole\Actions;

use App\Application\Cloud\ServerConsole\Actions\GetCloudServerConsoleAction;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\DTOs\CloudServerConsoleData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use Tests\TestCase;

final class GetCloudServerConsoleActionTest extends TestCase
{
    public function test_it_routes_console_to_the_servers_owning_provider(): void
    {
        $expected = new CloudServerConsoleData(
            url: 'https://console.example.test/vnc?token=test-token',
        );

        $console = $this->createMock(
            CloudServerConsoleInterface::class,
        );

        $console->expects($this->once())
            ->method('getVncConsole')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willReturn($expected);

        $providers = $this->createMock(
            CloudProviderRegistryInterface::class,
        );

        $providers->expects($this->once())
            ->method('resolveCapability')
            ->with(
                CloudProviderType::Arvan,
                CloudServerConsoleInterface::class,
            )
            ->willReturn($console);

        $server = new Server;
        $server->forceFill([
            'cloud_provider' => 'arvan',
            'cloud_region' => 'eu-west1-a',
            'cloud_server_id' => 'server-123',
        ]);

        $result = (new GetCloudServerConsoleAction(
            capabilities: new CloudServerCapabilityResolver(
                providers: $providers,
            ),
        ))->execute($server);

        $this->assertSame($expected, $result);
    }
}
