<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudPurchaseCatalogSourceInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use InvalidArgumentException;

final readonly class ResolveCloudImageForOrderAction
{
    public function __construct(
        private ?CloudProviderRegistryInterface $providers = null,
        private ?FilterSupportedCloudImagesAction $filter = null,
        private ?CloudProviderInterface $cloud = null,
        private ?ListSupportedCloudImagesAction $supportedImages = null,
    ) {}

    public function execute(
        string $region,
        string $sizeId,
        string $imageId,
        int $selectedDiskGiB,
        CloudProviderType $provider = CloudProviderType::Arvan,
    ): CloudImageData {
        $region = trim($region);
        $sizeId = trim($sizeId);
        $imageId = trim($imageId);

        if ($region === '') {
            throw new InvalidArgumentException(
                'Cloud region cannot be empty.',
            );
        }

        if ($sizeId === '') {
            throw new InvalidArgumentException(
                'Cloud size cannot be empty.',
            );
        }

        if ($imageId === '') {
            throw new InvalidArgumentException(
                'Cloud image cannot be empty.',
            );
        }

        if ($selectedDiskGiB < 1) {
            throw new InvalidArgumentException(
                'Selected disk must be greater than zero.',
            );
        }

        $cloud = $this->provider(
            $provider,
        );

        $size = $this->resolveSize(
            cloud: $cloud,
            region: $region,
            sizeId: $sizeId,
        );

        if ($selectedDiskGiB < $size->diskGiB) {
            throw new InvalidArgumentException(
                sprintf(
                    'Selected disk cannot be smaller than the size default of [%d] GiB.',
                    $size->diskGiB,
                ),
            );
        }

        $image = $this->resolveImage(
            cloud: $cloud,
            provider: $provider,
            region: $region,
            imageId: $imageId,
        );

        $minimumDiskGiB = max(
            $size->diskGiB,
            $image->minDiskGiB ?? 0,
        );

        if ($selectedDiskGiB < $minimumDiskGiB) {
            throw new InvalidArgumentException(
                sprintf(
                    'Selected image [%s] requires at least [%d] GiB of disk.',
                    $image->name,
                    $minimumDiskGiB,
                ),
            );
        }

        if (
            $image->minMemoryMiB !== null
            && $size->memoryMiB < $image->minMemoryMiB
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Selected size [%s] does not provide enough memory for image [%s].',
                    $size->name,
                    $image->name,
                ),
            );
        }

        return $image;
    }

    private function provider(
        CloudProviderType $provider,
    ): CloudProviderInterface {
        if ($this->providers instanceof CloudProviderRegistryInterface) {
            return $this->providers->resolve(
                $provider,
            );
        }

        /*
         * Transitional direct-construction seam for existing tests. Runtime
         * container resolution supplies the registry and remains provider
         * aware. A non-Arvan provider may never use this fallback.
         */
        if (
            $provider !== CloudProviderType::Arvan
            || ! $this->cloud instanceof CloudProviderInterface
        ) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud image dependencies for provider [%s] are not configured.',
                    $provider->value,
                ),
            );
        }

        return $this->cloud;
    }

    private function resolveSize(
        CloudProviderInterface $cloud,
        string $region,
        string $sizeId,
    ): CloudSizeData {
        $sizes = $cloud instanceof CloudPurchaseCatalogSourceInterface
            ? $cloud->listPurchaseSizes($region)
            : $cloud->listSizes($region);

        foreach ($sizes as $size) {
            if ($size->id === $sizeId) {
                return $size;
            }
        }

        throw new InvalidArgumentException(
            sprintf(
                'Cloud size [%s] was not found in region [%s].',
                $sizeId,
                $region,
            ),
        );
    }

    private function resolveImage(
        CloudProviderInterface $cloud,
        CloudProviderType $provider,
        string $region,
        string $imageId,
    ): CloudImageData {
        if ($this->providers instanceof CloudProviderRegistryInterface) {
            if (! $this->filter instanceof FilterSupportedCloudImagesAction) {
                throw new CloudConfigurationException(
                    'Cloud image filter is not configured.',
                );
            }

            $providerImages = $cloud instanceof CloudPurchaseCatalogSourceInterface
                ? $cloud->listPurchaseImages($region)
                : $cloud->listImages($region);

            $images = $this->filter->execute(
                $providerImages,
            );
        } else {
            if (
                $provider !== CloudProviderType::Arvan
                || ! $this->supportedImages instanceof ListSupportedCloudImagesAction
            ) {
                throw new CloudConfigurationException(
                    sprintf(
                        'Supported cloud image lookup for provider [%s] is not configured.',
                        $provider->value,
                    ),
                );
            }

            $images = $this->supportedImages->execute(
                $region,
            );
        }

        foreach ($images as $image) {
            if ($image->id === $imageId) {
                return $image;
            }
        }

        throw new InvalidArgumentException(
            sprintf(
                'Cloud image [%s] is unavailable or unsupported by xDeploy in region [%s].',
                $imageId,
                $region,
            ),
        );
    }
}
