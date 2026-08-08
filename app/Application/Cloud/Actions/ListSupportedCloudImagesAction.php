<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use InvalidArgumentException;

final readonly class ListSupportedCloudImagesAction
{
    public function __construct(
        private CloudProviderInterface $cloud,
        private SupportedOperatingSystemPolicy $operatingSystems,
    ) {}

    /**
     * @return list<CloudImageData>
     */
    public function execute(
        string $region,
    ): array {
        $region = trim($region);

        if ($region === '') {
            throw new InvalidArgumentException(
                'Cloud region cannot be empty.',
            );
        }

        $images = array_filter(
            $this->cloud->listImages(
                $region,
            ),
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
                 *
                 * Only explicitly verified distribution/version pairs
                 * may be exposed to the commercial purchase flow.
                 */
                return $this->operatingSystems
                    ->supportsIdVersion(
                        id: $image->distribution,
                        versionId: $image->version,
                    );
            },
        );

        return array_values(
            $images,
        );
    }
}
