<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Cloud\Actions\ResolveCloudProvisioningInfrastructureAction;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Models\Order;

final readonly class BuildCloudServerDataFromOrderAction
{
    public function __construct(
        private ResolveCloudProvisioningInfrastructureAction $resolveInfrastructure,
    ) {}

    public function execute(
        Order $order,
    ): CreateCloudServerData {
        $provider = $this->providerName();

        $prefix =
            "cloud.providers.{$provider}.defaults";

        $initializationScript = config(
            "{$prefix}.init_script",
            '',
        );

        if (! is_string($initializationScript)) {
            throw new CloudConfigurationException(
                'Cloud initialization script must be a string.',
            );
        }

        $highAvailability = filter_var(
            config(
                "{$prefix}.ha_enabled",
                false,
            ),
            FILTER_VALIDATE_BOOL,
        );

        /*
         * Network and security-group selection must follow the Order region.
         *
         * They are xDeploy infrastructure decisions rather than customer
         * purchase fields, but they still must be resolved from the same
         * provider region that the customer purchased.
         */
        $infrastructure =
            $this->resolveInfrastructure->execute(
                $order->region_id,
            );

        return new CreateCloudServerData(
            name: $this->serverName(
                $order,
            ),

            /*
             * Commercial selections always come from the immutable
             * Order snapshot. Never fall back to provider defaults.
             */
            regionId: $order->region_id,
            sizeId: $order->size_id,
            imageId: $order->image_id,

            /*
             * Provider infrastructure is resolved dynamically inside
             * the selected Order region.
             */
            networkId: $infrastructure->networkId,

            securityGroupIds: $infrastructure->securityGroupIds,

            diskGiB: $order->selected_disk_gib,

            sshKeyName: null,

            initializationScript: $initializationScript,

            highAvailability: $highAvailability,
        );
    }

    public function serverName(
        Order $order,
    ): string {
        return sprintf(
            'xdeploy-order-%d',
            $order->getKey(),
        );
    }

    public function providerName(): string
    {
        return $this->requiredConfigString(
            'cloud.default',
        );
    }

    private function requiredConfigString(
        string $key,
    ): string {
        $value = config(
            $key,
        );

        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            throw new CloudConfigurationException(
                sprintf(
                    'Required cloud configuration [%s] is missing.',
                    $key,
                ),
            );
        }

        return trim(
            $value,
        );
    }
}
