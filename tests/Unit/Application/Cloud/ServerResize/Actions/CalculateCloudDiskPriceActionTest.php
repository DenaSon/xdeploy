<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\CalculateCloudDiskPriceAction;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class CalculateCloudDiskPriceActionTest extends TestCase
{
    public function test_it_uses_the_servers_provider_and_region(): void
    {
        $price = new CloudPriceData(
            amount: '100',
            currency: 'IRR',
        );
        $expected = new CloudDiskPriceData(
            diskGiB: 40,
            hourlyPrice: $price,
            monthlyPrice: $price,
        );

        $catalog = $this->createMock(CloudServerResizeCatalogInterface::class);
        $catalog->expects($this->once())
            ->method('calculateDiskPrice')
            ->with('region-a', 'plan-1', 40)
            ->willReturn($expected);

        $providers = $this->createMock(CloudProviderRegistryInterface::class);
        $providers->expects($this->once())
            ->method('resolveCapability')
            ->with(CloudProviderType::Arvan, CloudServerResizeCatalogInterface::class)
            ->willReturn($catalog);

        $server = new Server();
        $server->forceFill([
            'cloud_provider' => 'arvan',
            'cloud_region' => 'region-a',
            'cloud_server_id' => 'server-1',
        ]);

        $result = (new CalculateCloudDiskPriceAction(
            capabilities: new CloudServerCapabilityResolver($providers),
        ))->handle($server, 'plan-1', 40);

        $this->assertSame($expected, $result);
    }
}
