<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Billing\Enums\OrderStatus;
use RuntimeException;

final class OrderNotProvisionableException extends RuntimeException
{
    public static function forStatus(
        int $orderId,
        OrderStatus $status,
    ): self {
        return new self(
            sprintf(
                'Order [%d] cannot be provisioned while its status is [%s].',
                $orderId,
                $status->value,
            ),
        );
    }

    public static function alreadyProvisioning(
        int $orderId,
    ): self {
        return new self(
            sprintf(
                'Order [%d] is already provisioning and cannot start another cloud-server creation attempt automatically.',
                $orderId,
            ),
        );
    }

    public static function fulfilledWithoutServer(
        int $orderId,
    ): self {
        return new self(
            sprintf(
                'Order [%d] is already fulfilled but its Server relation is no longer available.',
                $orderId,
            ),
        );
    }
}
