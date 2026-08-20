<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\SubmitCloudServerResizeAction;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\DTOs\ResizeCloudServerData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use PHPUnit\Framework\TestCase;

final class SubmitCloudServerResizeActionTest extends TestCase
{
    public function test_it_routes_resize_to_owner_and_replaces_caller_resource_identity(): void
    {
        $resizer = $this->createMock(CloudServerResizerInterface::class);
        $resizer->expects($this->once())
            ->method('resizeServer')
            ->with($this->callback(
                static fn (ResizeCloudServerData $data): bool => $data->regionId === 'iran'
                    && $data->serverId === 'liara-vm-123'
                    && $data->targetSizeId === 'target-plan'
                    && $data->targetDiskGiB === 40,
            ));

        $providers = $this->createMock(CloudProviderRegistryInterface::class);
        $providers->expects($this->once())
            ->method('resolveCapability')
            ->with(CloudProviderType::Liara, CloudServerResizerInterface::class)
            ->willReturn($resizer);

        $server = new Server;
        $server->forceFill([
            'cloud_provider' => 'liara',
            'cloud_region' => 'iran',
            'cloud_server_id' => 'liara-vm-123',
        ]);

        (new SubmitCloudServerResizeAction(
            capabilities: new CloudServerCapabilityResolver($providers),
        ))->handle(
            $server,
            new ResizeCloudServerData(
                regionId: 'wrong-region',
                serverId: 'wrong-server',
                targetSizeId: 'target-plan',
                targetDiskGiB: 40,
            ),
        );
    }
}
