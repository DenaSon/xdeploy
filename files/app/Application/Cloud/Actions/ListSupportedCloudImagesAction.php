<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use InvalidArgumentException;

final readonly class ListSupportedCloudImagesAction
{
    public function __construct(
        private CloudProviderInterface $cloud,
        private FilterSupportedCloudImagesAction $filter,
    ) {}

    /**
     * Authoritative provider-backed lookup.
     *
     * This action intentionally bypasses the presentation catalog cache
     * because order creation must re-resolve the current provider image.
     *
     * @return list<CloudImageData>
     */
    public function execute(
        string $region,
    ): array {
        $region = trim(
            $region,
        );

        if ($region === '') {
            throw new InvalidArgumentException(
                'Cloud region cannot be empty.',
            );
        }

        return $this->filter->execute(
            $this->cloud->listImages(
                $region,
            ),
        );
    }
}
