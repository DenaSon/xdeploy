<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Models\Order;
use LogicException;

final readonly class BuildCloudServerDataFromOrderAction
{
    public function execute(Order $order): CreateCloudServerData
    {
        $provider = $this->providerName($order);
        $prefix = "cloud.providers.{$provider}.defaults";

        $initializationScript = config("{$prefix}.init_script", '');

        if (! is_string($initializationScript)) {
            throw new CloudConfigurationException(
                'Cloud initialization script must be a string.',
            );
        }

        $highAvailability = filter_var(
            config("{$prefix}.ha_enabled", false),
            FILTER_VALIDATE_BOOL,
        );

        return new CreateCloudServerData(
            name: $this->serverName($order),
            regionId: $order->region_id,
            sizeId: $order->size_id,
            imageId: $order->image_id,
            diskGiB: $order->selected_disk_gib,
            sshKeyName: null,
            initializationScript: $initializationScript,
            highAvailability: $highAvailability,
        );
    }

    public function serverName(Order $order): string
    {
        $orderId = $order->getKey();

        if (! is_int($orderId) || $orderId < 1) {
            throw new LogicException(
                'Cloud server names require a persisted order identifier.',
            );
        }

        /*
         * Provider-safe, deterministic and compact. Liara currently limits VM
         * names to 19 characters, while Arvan also accepts this hostname-safe
         * shape. Base36 keeps even a 64-bit order id comfortably below that
         * limit without truncation or collision-prone hashing.
         */
        return sprintf(
            'cf-%s',
            base_convert(
                (string) $orderId,
                10,
                36,
            ),
        );
    }

    public function providerName(Order $order): string
    {
        $provider = $order->cloud_provider;

        if (! $provider instanceof CloudProviderType) {
            throw new LogicException(
                sprintf(
                    'Order [%d] has no valid cloud provider.',
                    $order->getKey(),
                ),
            );
        }

        return $provider->value;
    }
}
