<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\DTOs\CloudVolumeAttachmentData;
use App\Domain\Cloud\DTOs\CloudVolumeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Admin\CloudVolumes\Index;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

final class AdminCloudVolumeAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_volume_relationships_and_orphans(): void
    {
        config()->set('cloud.providers.arvan.region', 'region-a');

        $admin = $this->admin();
        $server = $this->cloudServer();
        $manager = new AdminAuditVolumeManagerFake([
            new CloudVolumeData(
                id: 'vol-linked',
                name: 'Linked volume',
                regionId: 'region-a',
                status: 'in-use',
                attachments: [
                    new CloudVolumeAttachmentData(
                        id: 'att-1',
                        serverId: 'srv-linked',
                        serverName: 'Provider server',
                    ),
                ],
            ),
            new CloudVolumeData(
                id: 'vol-orphan',
                name: 'Orphan volume',
                regionId: 'region-a',
                status: 'available',
            ),
        ]);
        $this->bindRegistry($manager);

        $this->actingAs($admin)
            ->get(route('admin.cloud-volumes.index'))
            ->assertOk()
            ->assertSee('بررسی Volumeها')
            ->assertSee('Linked volume')
            ->assertSee('Orphan volume')
            ->assertSee($server->name)
            ->assertSee('srv-linked');
    }

    public function test_admin_can_manually_delete_orphan_volume(): void
    {
        config()->set('cloud.providers.arvan.region', 'region-a');

        $admin = $this->admin();
        $manager = new AdminAuditVolumeManagerFake([
            new CloudVolumeData(
                id: 'vol-orphan',
                name: 'Orphan volume',
                regionId: 'region-a',
                status: 'available',
            ),
        ]);
        $this->bindRegistry($manager);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertSee('Orphan volume')
            ->call('deleteVolume', 'region-a', 'vol-orphan')
            ->assertSet(
                'message',
                'Volume از آروان حذف و نبودن آن تأیید شد.',
            )
            ->assertDontSee('Orphan volume');

        $this->assertSame(['vol-orphan'], $manager->deletedVolumeIds);
    }

    public function test_admin_cannot_delete_linked_volume(): void
    {
        config()->set('cloud.providers.arvan.region', 'region-a');

        $admin = $this->admin();
        $this->cloudServer();
        $manager = new AdminAuditVolumeManagerFake([
            new CloudVolumeData(
                id: 'vol-linked',
                name: 'Linked volume',
                regionId: 'region-a',
                status: 'in-use',
                attachments: [
                    new CloudVolumeAttachmentData(
                        id: 'att-1',
                        serverId: 'srv-linked',
                    ),
                ],
            ),
        ]);
        $this->bindRegistry($manager);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('deleteVolume', 'region-a', 'vol-linked')
            ->assertSet(
                'error',
                'این Volume در وضعیت فعلی قابل حذف نیست. ابتدا گزارش را بازبینی کنید.',
            );

        $this->assertSame([], $manager->deletedVolumeIds);
    }

    private function bindRegistry(AdminAuditVolumeManagerFake $manager): void
    {
        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new AdminAuditRegistryFake($manager),
        );
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->saveOrFail();

        return $admin;
    }

    private function cloudServer(): Server
    {
        $user = User::factory()->create();

        return $user->servers()->create([
            'name' => 'Coreflare linked server',
            'host' => '203.0.113.22',
            'port' => 22,
            'username' => 'ubuntu',
            'status' => ServerStatus::Active,
            'cloud_provider' => CloudProviderType::Arvan,
            'cloud_server_id' => 'srv-linked',
            'cloud_region' => 'region-a',
            'provisioned_at' => now()->subDay(),
        ]);
    }
}

final readonly class AdminAuditRegistryFake implements CloudProviderRegistryInterface
{
    public function __construct(
        private AdminAuditVolumeManagerFake $manager,
    ) {}

    public function registeredProviders(): array
    {
        return [CloudProviderType::Arvan];
    }

    public function purchasableProviders(): array
    {
        return [CloudProviderType::Arvan];
    }

    public function resolve(CloudProviderType $provider): CloudProviderInterface
    {
        throw new LogicException('Provider resolution is not used by this test.');
    }

    public function resolveCapability(
        CloudProviderType $provider,
        string $capability,
    ): object {
        if ($capability === CloudVolumeManagerInterface::class) {
            return $this->manager;
        }

        throw new LogicException('Unexpected capability: '.$capability);
    }

    public function supportsCapability(
        CloudProviderType $provider,
        string $capability,
    ): bool {
        return $capability === CloudVolumeManagerInterface::class;
    }
}

final class AdminAuditVolumeManagerFake implements CloudVolumeManagerInterface
{
    /** @var list<CloudVolumeData> */
    public array $volumes;

    /** @var list<string> */
    public array $deletedVolumeIds = [];

    /** @param list<CloudVolumeData> $volumes */
    public function __construct(array $volumes)
    {
        $this->volumes = $volumes;
    }

    public function listVolumes(string $region): array
    {
        return array_values(array_filter(
            $this->volumes,
            static fn (CloudVolumeData $volume): bool => $volume->regionId === $region,
        ));
    }

    public function listAttachedToServer(
        string $region,
        string $serverId,
    ): array {
        return array_values(array_filter(
            $this->listVolumes($region),
            static fn (CloudVolumeData $volume): bool => $volume->isAttachedTo($serverId),
        ));
    }

    public function findVolume(
        string $region,
        string $volumeId,
    ): CloudVolumeData {
        foreach ($this->listVolumes($region) as $volume) {
            if ($volume->id === $volumeId) {
                return $volume;
            }
        }

        throw new CloudResourceNotFoundException('Volume not found.');
    }

    public function deleteVolume(
        string $region,
        string $volumeId,
    ): void {
        $remaining = [];
        $found = false;

        foreach ($this->volumes as $volume) {
            if ($volume->regionId === $region && $volume->id === $volumeId) {
                $found = true;
                continue;
            }

            $remaining[] = $volume;
        }

        if (! $found) {
            throw new CloudResourceNotFoundException('Volume not found.');
        }

        $this->volumes = $remaining;
        $this->deletedVolumeIds[] = $volumeId;
    }
}
