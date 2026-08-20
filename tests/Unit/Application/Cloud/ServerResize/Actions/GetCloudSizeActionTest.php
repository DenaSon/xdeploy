<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\GetCloudSizeAction;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class GetCloudSizeActionTest extends TestCase
{
    public function test_it_uses_the_servers_provider_and_region(): void
    {
        $expected = new CloudSizeData(
            id: 'standard-base-g2',
            name: 'standard-base-g2',
            regionId: 'iran',
            vCpu: 1,
            memoryMiB: 2048,
            diskGiB: 20,
            category: null,
            hourlyPrice: null,
            monthlyPrice: null,
        );

        $catalog = $this->createMock(CloudServerResizeCatalogInterface::class);
        $catalog->expects($this->once())
            ->method('findSize')
            ->with('iran', 'standard-base-g2')
            ->willReturn($expected);

        $providers = $this->createMock(CloudProviderRegistryInterface::class);
        $providers->expects($this->once())
            ->method('resolveCapability')
            ->with(CloudProviderType::Liara, CloudServerResizeCatalogInterface::class)
            ->willReturn($catalog);

        $server = new Server;
        $server->forceFill([
            'cloud_provider' => 'liara',
            'cloud_region' => 'iran',
            'cloud_server_id' => 'liara-vm-123',
        ]);

        $result = (new GetCloudSizeAction(
            capabilities: new CloudServerCapabilityResolver($providers),
        ))->handle($server, 'standard-base-g2');

        $this->assertSame($expected, $result);
    }
}
