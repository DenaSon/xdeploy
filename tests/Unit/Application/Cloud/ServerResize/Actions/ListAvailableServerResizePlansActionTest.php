<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\ListAvailableServerResizePlansAction;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class ListAvailableServerResizePlansActionTest extends TestCase
{
    public function test_it_routes_resize_catalog_to_the_servers_owning_provider(): void
    {
        $catalog = $this->createMock(
            CloudServerResizeCatalogInterface::class,
        );

        $catalog->expects($this->once())
            ->method('listServerResizePlans')
            ->with('iran', 'liara-vm-123')
            ->willReturn([]);

        $providers = $this->createMock(
            CloudProviderRegistryInterface::class,
        );

        $providers->expects($this->once())
            ->method('resolveCapability')
            ->with(
                CloudProviderType::Liara,
                CloudServerResizeCatalogInterface::class,
            )
            ->willReturn($catalog);

        $server = new Server();
        $server->forceFill([
            'cloud_provider' => 'liara',
            'cloud_region' => 'iran',
            'cloud_server_id' => 'liara-vm-123',
        ]);

        $result = (new ListAvailableServerResizePlansAction(
            capabilities: new CloudServerCapabilityResolver(
                providers: $providers,
            ),
        ))->handle($server);

        $this->assertSame([], $result);
    }
}
