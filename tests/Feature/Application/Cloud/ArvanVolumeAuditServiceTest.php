<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Volumes\ArvanVolumeAuditService;
use App\Application\Cloud\Volumes\DeleteArvanAuditedVolumeAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\DTOs\CloudVolumeAttachmentData;
use App\Domain\Cloud\DTOs\CloudVolumeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Enums\CloudVolumeAuditStatus;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ArvanVolumeAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_linked_detached_orphan_and_ambiguous_volumes(): void
    {
        config()->set('cloud.providers.arvan.region', 'region-a');

        $user = User::factory()->create();
        $linkedServer = $this->server(
            user: $user,
            name: 'Active server',
            providerServerId: 'srv-linked',
            status: ServerStatus::Active,
        );
        $detachedServer = $this->server(
            user: $user,
            name: 'Old server',
            providerServerId: 'srv-old',
            status: ServerStatus::Inactive,
            terminationVolumeIds: ['vol-detached'],
        );
        $detachedServer->forceFill([
            'terminated_at' => now(),
        ])->saveOrFail();
        $detachedServer->delete();

        $manager = new AuditVolumeManagerFake([
            'region-a' => [
                new CloudVolumeData(
                    id: 'vol-linked',
                    name: 'Linked volume',
                    regionId: 'region-a',
                    status: 'in-use',
                    attachments: [
                        new CloudVolumeAttachmentData(
                            id: 'att-1',
                            serverId: 'srv-linked',
                            serverName: 'Provider linked server',
                        ),
                    ],
                ),
                new CloudVolumeData(
                    id: 'vol-detached',
                    name: 'Detached volume',
                    regionId: 'region-a',
                    status: 'available',
                ),
                new CloudVolumeData(
                    id: 'vol-orphan',
                    name: 'Orphan volume',
                    regionId: 'region-a',
                    status: 'available',
                ),
                new CloudVolumeData(
                    id: 'vol-ambiguous',
                    name: 'Unknown attached volume',
                    regionId: 'region-a',
                    status: 'in-use',
                    attachments: [
                        new CloudVolumeAttachmentData(
                            id: 'att-2',
                            serverId: 'srv-external',
                            serverName: 'External server',
                        ),
                    ],
                ),
            ],
        ]);

        $items = collect(
            (new ArvanVolumeAuditService(
                new AuditRegistryFake($manager),
            ))->audit(),
        )->keyBy(fn ($item) => $item->volumeId);

        $this->assertSame(
            CloudVolumeAuditStatus::Linked,
            $items['vol-linked']->auditStatus,
        );
        $this->assertSame(
            $linkedServer->getKey(),
            $items['vol-linked']->coreflareServerId,
        );
        $this->assertFalse($items['vol-linked']->canDelete());

        $this->assertSame(
            CloudVolumeAuditStatus::Detached,
            $items['vol-detached']->auditStatus,
        );
        $this->assertSame(
            $detachedServer->getKey(),
            $items['vol-detached']->coreflareServerId,
        );
        $this->assertTrue($items['vol-detached']->coreflareServerDeleted);
        $this->assertTrue($items['vol-detached']->canDelete());

        $this->assertSame(
            CloudVolumeAuditStatus::Orphan,
            $items['vol-orphan']->auditStatus,
        );
        $this->assertNull($items['vol-orphan']->coreflareServerId);
        $this->assertTrue($items['vol-orphan']->canDelete());

        $this->assertSame(
            CloudVolumeAuditStatus::Ambiguous,
            $items['vol-ambiguous']->auditStatus,
        );
        $this->assertSame(
            'srv-external',
            $items['vol-ambiguous']->attachmentServerId,
        );
        $this->assertFalse($items['vol-ambiguous']->canDelete());
    }

    public function test_manual_delete_revalidates_audit_state_and_verifies_provider_absence(): void
    {
        config()->set('cloud.providers.arvan.region', 'region-a');

        $manager = new AuditVolumeManagerFake([
            'region-a' => [
                new CloudVolumeData(
                    id: 'vol-orphan',
                    name: 'Orphan volume',
                    regionId: 'region-a',
                    status: 'available',
                ),
            ],
        ]);
        $registry = new AuditRegistryFake($manager);
        $audit = new ArvanVolumeAuditService($registry);
        $action = new DeleteArvanAuditedVolumeAction(
            audit: $audit,
            providers: $registry,
        );

        $this->assertTrue(
            $action->handle('region-a', 'vol-orphan'),
        );
        $this->assertSame(
            ['vol-orphan'],
            $manager->deletedVolumeIds,
        );
        $this->assertNull(
            $audit->find('region-a', 'vol-orphan'),
        );
    }

    public function test_manual_delete_refuses_linked_volume(): void
    {
        config()->set('cloud.providers.arvan.region', 'region-a');

        $user = User::factory()->create();
        $this->server(
            user: $user,
            name: 'Active server',
            providerServerId: 'srv-linked',
            status: ServerStatus::Active,
        );

        $manager = new AuditVolumeManagerFake([
            'region-a' => [
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
            ],
        ]);
        $registry = new AuditRegistryFake($manager);
        $action = new DeleteArvanAuditedVolumeAction(
            audit: new ArvanVolumeAuditService($registry),
            providers: $registry,
        );

        $this->expectException(CloudValidationException::class);

        $action->handle('region-a', 'vol-linked');
    }

    public function test_manual_delete_uses_exact_lookup_when_volume_is_missing_from_collection(): void
    {
        config()->set('cloud.providers.arvan.region', 'region-a');

        $manager = new AuditVolumeManagerFake([
            'region-a' => [],
        ]);
        $manager->pointLookupOnlyVolumesByRegion = [
            'region-a' => [
                'vol-hidden' => new CloudVolumeData(
                    id: 'vol-hidden',
                    name: 'Point lookup only volume',
                    regionId: 'region-a',
                    status: 'available',
                ),
            ],
        ];
        $registry = new AuditRegistryFake($manager);
        $audit = new ArvanVolumeAuditService($registry);
        $action = new DeleteArvanAuditedVolumeAction(
            audit: $audit,
            providers: $registry,
        );

        $this->assertNull($audit->find('region-a', 'vol-hidden'));
        $this->assertTrue(
            $audit->findExact('region-a', 'vol-hidden')?->canDelete(),
        );
        $this->assertTrue(
            $action->handle('region-a', 'vol-hidden'),
        );
        $this->assertSame(
            ['vol-hidden'],
            $manager->deletedVolumeIds,
        );
        $this->assertNull(
            $audit->findExact('region-a', 'vol-hidden'),
        );
    }

    private function server(
        User $user,
        string $name,
        string $providerServerId,
        ServerStatus $status,
        array $terminationVolumeIds = [],
    ): Server {
        return $user->servers()->create([
            'name' => $name,
            'host' => '203.0.113.10',
            'port' => $providerServerId === 'srv-linked'
                ? 22
                : 2222,
            'username' => 'ubuntu',
            'status' => $status,
            'cloud_provider' => CloudProviderType::Arvan,
            'cloud_server_id' => $providerServerId,
            'cloud_region' => 'region-a',
            'termination_volume_ids' => $terminationVolumeIds === []
                ? null
                : $terminationVolumeIds,
            'provisioned_at' => now()->subDay(),
        ]);
    }
}

final readonly class AuditRegistryFake implements CloudProviderRegistryInterface
{
    public function __construct(
        private AuditVolumeManagerFake $manager,
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

final class AuditVolumeManagerFake implements CloudVolumeManagerInterface
{
    /** @var array<string, list<CloudVolumeData>> */
    public array $volumesByRegion;

    /** @var array<string, array<string, CloudVolumeData>> */
    public array $pointLookupOnlyVolumesByRegion = [];

    /** @var list<string> */
    public array $deletedVolumeIds = [];

    /** @param array<string, list<CloudVolumeData>> $volumesByRegion */
    public function __construct(array $volumesByRegion)
    {
        $this->volumesByRegion = $volumesByRegion;
    }

    public function listVolumes(string $region): array
    {
        return array_values($this->volumesByRegion[$region] ?? []);
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
        if (isset($this->pointLookupOnlyVolumesByRegion[$region][$volumeId])) {
            return $this->pointLookupOnlyVolumesByRegion[$region][$volumeId];
        }

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
        if (isset($this->pointLookupOnlyVolumesByRegion[$region][$volumeId])) {
            unset($this->pointLookupOnlyVolumesByRegion[$region][$volumeId]);
            $this->deletedVolumeIds[] = $volumeId;

            return;
        }

        $remaining = [];
        $found = false;

        foreach ($this->listVolumes($region) as $volume) {
            if ($volume->id === $volumeId) {
                $found = true;
                continue;
            }

            $remaining[] = $volume;
        }

        if (! $found) {
            throw new CloudResourceNotFoundException('Volume not found.');
        }

        $this->volumesByRegion[$region] = $remaining;
        $this->deletedVolumeIds[] = $volumeId;
    }
}
