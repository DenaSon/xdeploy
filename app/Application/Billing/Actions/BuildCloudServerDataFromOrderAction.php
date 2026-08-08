<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Models\Order;

final readonly class BuildCloudServerDataFromOrderAction
{
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
             * Network/security configuration is an xDeploy
             * infrastructure decision, not a customer purchase field.
             */
            networkId: $this->requiredConfigString(
                "{$prefix}.network_id",
            ),

            securityGroupIds: [
                $this->requiredConfigString(
                    "{$prefix}.security_group_id",
                ),
            ],

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
