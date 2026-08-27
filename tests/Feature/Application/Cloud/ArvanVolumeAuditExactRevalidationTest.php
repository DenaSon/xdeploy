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
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ArvanVolumeAuditExactRevalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_delete_fails_closed_when_exact_lookup_reports_a_fresh_attachment(): void
    {
        config()->set('cloud.providers.arvan.region', 'region-a');

        $listedVolume = new CloudVolumeData(
            id: 'vol-race',
            name: 'Race volume',
            regionId: 'region-a',
            status: 'available',
        );
        $exactVolume = new CloudVolumeData(
            id: 'vol-race',
            name: 'Race volume',
            regionId: 'region-a',
            status: 'in-use',
            attachments: [
                new CloudVolumeAttachmentData(
                    id: 'att-race',
                    serverId: 'srv-external',
                    serverName: 'Freshly attached server',
                ),
            ],
        );

        $manager = new ExactRevalidationVolumeManagerFake(
            listedVolume: $listedVolume,
            exactVolume: $exactVolume,
        );
        $registry = new ExactRevalidationRegistryFake($manager);
        $audit = new ArvanVolumeAuditService($registry);
        $action = new DeleteArvanAuditedVolumeAction(
            audit: $audit,
            providers: $registry,
        );

        $this->assertTrue(
            $audit->find('region-a', 'vol-race')?->canDelete(),
            'The collection endpoint intentionally represents the stale detached state.',
        );
        $this->assertFalse(
            $audit->findExact('region-a', 'vol-race')?->canDelete(),
            'The exact endpoint must win before a destructive mutation.',
        );

        try {
            $action->handle('region-a', 'vol-race');
            $this->fail('Expected exact revalidation to refuse deletion.');
        } catch (CloudValidationException) {
            $this->assertSame([], $manager->deletedVolumeIds);
        }
    }
}

final readonly class ExactRevalidationRegistryFake implements CloudProviderRegistryInterface
{
    public function __construct(
        private ExactRevalidationVolumeManagerFake $manager,
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

final class ExactRevalidationVolumeManagerFake implements CloudVolumeManagerInterface
{
    /** @var list<string> */
    public array $deletedVolumeIds = [];

    public function __construct(
        private readonly CloudVolumeData $listedVolume,
        private readonly CloudVolumeData $exactVolume,
    ) {}

    public function listVolumes(string $region): array
    {
        return $region === $this->listedVolume->regionId
            ? [$this->listedVolume]
            : [];
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
        if (
            $region === $this->exactVolume->regionId
            && $volumeId === $this->exactVolume->id
        ) {
            return $this->exactVolume;
        }

        throw new CloudResourceNotFoundException('Volume not found.');
    }

    public function deleteVolume(
        string $region,
        string $volumeId,
    ): void {
        $this->deletedVolumeIds[] = $volumeId;
    }
}
