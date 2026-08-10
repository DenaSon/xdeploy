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
        return array_values(
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
    }
}
