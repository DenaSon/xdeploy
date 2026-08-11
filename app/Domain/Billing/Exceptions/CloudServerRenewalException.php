<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use App\Domain\Billing\Enums\OrderStatus;
use RuntimeException;

final class CloudServerRenewalException extends RuntimeException
{
    public static function serverNotRenewable(
        int $serverId,
    ): self {
        return new self(
            sprintf(
                'Cloud Server [%d] is not eligible for renewal.',
                $serverId,
            ),
        );
    }

    public static function serverExpired(
        int $serverId,
    ): self {
        return new self(
            sprintf(
                'Cloud Server [%d] has already expired and cannot start a new renewal payment.',
                $serverId,
            ),
        );
    }

    public static function terminationStarted(
        int $serverId,
    ): self {
        return new self(
            sprintf(
                'Cloud Server [%d] cannot be renewed after termination has started.',
                $serverId,
            ),
        );
    }

    public static function sourceOrderMissing(
        int $serverId,
    ): self {
        return new self(
            sprintf(
                'Cloud Server [%d] has no fulfilled provisioning Order to price a renewal from.',
                $serverId,
            ),
        );
    }

    public static function wrongOrderType(
        int $orderId,
    ): self {
        return new self(
            sprintf(
                'Order [%d] is not a Cloud Server renewal Order.',
                $orderId,
            ),
        );
    }

    public static function forOrderStatus(
        int $orderId,
        OrderStatus $status,
    ): self {
        return new self(
            sprintf(
                'Renewal Order [%d] cannot be fulfilled while its status is [%s].',
                $orderId,
                $status->value,
            ),
        );
    }

    public static function serverUnavailableForOrder(
        int $orderId,
    ): self {
        return new self(
            sprintf(
                'Renewal Order [%d] no longer has an available Cloud Server to renew.',
                $orderId,
            ),
        );
    }

    public static function ownershipMismatch(
        int $orderId,
        int $serverId,
    ): self {
        return new self(
            sprintf(
                'Renewal Order [%d] does not belong to Cloud Server [%d] owner.',
                $orderId,
                $serverId,
            ),
        );
    }
}
