<?php

declare(strict_types=1);

namespace App\Application\Cloud\Volumes;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudValidationException;

final readonly class DeleteArvanAuditedVolumeAction
{
    public function __construct(
        private ArvanVolumeAuditService $audit,
        private CloudProviderRegistryInterface $providers,
    ) {}

    /**
     * Returns true when provider absence is verified. False means Arvan
     * accepted deletion but the volume is still visible and needs a refresh.
     */
    public function handle(
        string $region,
        string $volumeId,
    ): bool {
        $item = $this->audit->find(
            region: $region,
            volumeId: $volumeId,
        );

        if ($item === null) {
            return true;
        }

        if (! $item->canDelete()) {
            throw new CloudValidationException(
                'Cloud volume is not eligible for manual audit deletion.',
            );
        }

        /** @var CloudVolumeManagerInterface $manager */
        $manager = $this->providers->resolveCapability(
            provider: CloudProviderType::Arvan,
            capability: CloudVolumeManagerInterface::class,
        );

        try {
            $manager->deleteVolume(
                region: $region,
                volumeId: $volumeId,
            );
        } catch (CloudResourceNotFoundException) {
            return true;
        }

        try {
            $volume = $manager->findVolume(
                region: $region,
                volumeId: $volumeId,
            );
        } catch (CloudResourceNotFoundException) {
            return true;
        }

        return strtolower(trim($volume->status)) === 'deleted';
    }
}
