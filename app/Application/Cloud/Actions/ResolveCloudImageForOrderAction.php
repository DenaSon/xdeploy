<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use InvalidArgumentException;

final readonly class ResolveCloudImageForOrderAction
{
    public function __construct(
        private CloudProviderInterface $cloud,
        private ListSupportedCloudImagesAction $supportedImages,
    ) {}

    public function execute(
        string $region,
        string $sizeId,
        string $imageId,
        int $selectedDiskGiB,
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

        $size = $this->resolveSize(
            region: $region,
            sizeId: $sizeId,
        );

        if (
            $selectedDiskGiB
            < $size->diskGiB
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    'Selected disk cannot be smaller than the size default of [%d] GiB.',
                    $size->diskGiB,
                ),
            );
        }

        $image = $this->resolveImage(
            region: $region,
            imageId: $imageId,
        );

        $minimumDiskGiB = max(
            $size->diskGiB,
            $image->minDiskGiB ?? 0,
        );

        if (
            $selectedDiskGiB
            < $minimumDiskGiB
        ) {
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
            && $size->memoryMiB
            < $image->minMemoryMiB
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

    private function resolveSize(
        string $region,
        string $sizeId,
    ): CloudSizeData {
        foreach (
            $this->cloud->listSizes(
                $region,
            )
            as $size
        ) {
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
        string $region,
        string $imageId,
    ): CloudImageData {
        foreach (
            $this->supportedImages->execute(
                $region,
            )
            as $image
        ) {
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
