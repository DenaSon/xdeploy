<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Servers\DeleteCloudServerAction;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class ArvanCloudEmptyVolumeCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_arvan_delete_fails_closed_when_no_volume_targets_can_be_snapshotted(): void
    {
        $user = User::factory()->create();
        $server = $user->servers()->create([
            'name' => 'Arvan VPS',
            'host' => '203.0.113.91',
            'port' => 22,
            'username' => 'ubuntu',
            'status' => ServerStatus::Active,
            'cloud_provider' => CloudProviderType::Arvan,
            'cloud_server_id' => 'cloud-server-empty-volume-test',
            'cloud_region' => 'eu-west1-a',
            'provisioned_at' => now()->subDay(),
        ]);

        $lifecycle = $this->createMock(CloudServerLifecycleInterface::class);
        $lifecycle->expects($this->never())
            ->method('deleteServer');

        $volumes = $this->createMock(CloudVolumeManagerInterface::class);
        $volumes->expects($this->once())
            ->method('listAttachedToServer')
            ->with('eu-west1-a', 'cloud-server-empty-volume-test')
            ->willReturn([]);
        $volumes->expects($this->never())
            ->method('deleteVolume');

        $registry = $this->registry(
            lifecycle: $lifecycle,
            volumes: $volumes,
        );

        $action = new DeleteCloudServerAction(
            lifecycle: $lifecycle,
            providers: $registry,
        );

        try {
            $action->handle(
                user: $user,
                serverId: (int) $server->getKey(),
            );

            $this->fail('Expected missing Arvan volume targets to fail closed.');
        } catch (CloudValidationException $exception) {
            $this->assertSame(
                'ArvanCloud volume cleanup targets could not be determined.',
                $exception->getMessage(),
            );
        }

        $fresh = Server::query()->findOrFail($server->getKey());
        $this->assertNull($fresh->terminated_at);
        $this->assertNull($fresh->deleted_at);
        $this->assertNull($fresh->termination_volume_ids);
    }

    private function registry(
        CloudServerLifecycleInterface $lifecycle,
        CloudVolumeManagerInterface $volumes,
    ): CloudProviderRegistryInterface&MockObject {
        $registry = $this->createMock(CloudProviderRegistryInterface::class);

        $registry->method('resolveCapability')
            ->willReturnCallback(
                static fn (
                    CloudProviderType $provider,
                    string $capability,
                ): object => match ($capability) {
                    CloudServerLifecycleInterface::class => $lifecycle,
                    CloudVolumeManagerInterface::class => $volumes,
                    default => throw new \LogicException('Unexpected capability: '.$capability),
                },
            );

        return $registry;
    }
}
