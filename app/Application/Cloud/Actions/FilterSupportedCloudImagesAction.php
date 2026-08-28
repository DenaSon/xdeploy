<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;

final readonly class FilterSupportedCloudImagesAction
{
    public function __construct(
        private SupportedOperatingSystemPolicy $operatingSystems,
    ) {}

    /**
     * @param  list<CloudImageData>  $images
     * @return list<CloudImageData>
     */
    public function execute(
        array $images,
    ): array {
        $supported = array_values(
            array_filter(
                $images,
                function (
                    CloudImageData $image,
                ): bool {
                    /*
                     * Current automated provisioning relies on the
                     * generated password returned by the cloud provider.
                     */
                    if (! $image->supportsPassword) {
                        return false;
                    }

                    /*
                     * Provider availability does not imply xDeploy support.
                     * Only explicitly verified distribution/version pairs
                     * may be exposed to the commercial purchase flow.
                     */
                    return $this->operatingSystems
                        ->supportsIdVersion(
                            id: $image->distribution,
                            versionId: $image->version,
                        );
                },
            ),
        );

        $ranked = array_map(
            static fn (CloudImageData $image, int $index): array => [
                'image' => $image,
                'index' => $index,
            ],
            $supported,
            array_keys($supported),
        );

        usort(
            $ranked,
            static fn (array $left, array $right): int => [
                self::purchasePreference($left['image']),
                $left['index'],
            ] <=> [
                self::purchasePreference($right['image']),
                $right['index'],
            ],
        );

        return array_map(
            static fn (array $item): CloudImageData => $item['image'],
            $ranked,
        );
    }

    private static function purchasePreference(
        CloudImageData $image,
    ): int {
        $distribution = strtolower(trim($image->distribution));
        $version = trim($image->version);

        if (
            $distribution === 'ubuntu'
            && str_starts_with($version, '24.04')
        ) {
            return 0;
        }

        if ($distribution === 'ubuntu') {
            return 1;
        }

        return 2;
    }
}
