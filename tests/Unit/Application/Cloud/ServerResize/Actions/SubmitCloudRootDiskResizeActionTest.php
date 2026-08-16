<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\SubmitCloudRootDiskResizeAction;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\DTOs\ResizeCloudRootDiskData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class SubmitCloudRootDiskResizeActionTest extends TestCase
{
    public function test_it_routes_root_disk_resize_to_owner_and_replaces_resource_identity(): void
    {
        $resizer = $this->createMock(CloudServerResizerInterface::class);
        $resizer->expects($this->once())
            ->method('resizeRootDisk')
            ->with($this->callback(
                static fn (ResizeCloudRootDiskData $data): bool =>
                    $data->regionId === 'region-a'
                    && $data->serverId === 'server-1'
                    && $data->targetDiskGiB === 80,
            ));

        $providers = $this->createMock(CloudProviderRegistryInterface::class);
        $providers->expects($this->once())
            ->method('resolveCapability')
            ->with(CloudProviderType::Arvan, CloudServerResizerInterface::class)
            ->willReturn($resizer);

        $server = new Server();
        $server->forceFill([
            'cloud_provider' => 'arvan',
            'cloud_region' => 'region-a',
            'cloud_server_id' => 'server-1',
        ]);

        (new SubmitCloudRootDiskResizeAction(
            capabilities: new CloudServerCapabilityResolver($providers),
        ))->handle(
            $server,
            new ResizeCloudRootDiskData(
                regionId: 'wrong-region',
                serverId: 'wrong-server',
                targetDiskGiB: 80,
            ),
        );
    }
}
