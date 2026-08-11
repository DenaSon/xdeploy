<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;

final class CloudServerRenewalException extends DomainException
{
    public static function notRenewable(int $serverId): self
    {
        return new self(
            "Cloud Server [{$serverId}] is not eligible for renewal.",
        );
    }

    public static function purchaseSnapshotMissing(int $serverId): self
    {
        return new self(
            "Cloud Server [{$serverId}] has no fulfilled purchase snapshot for renewal pricing.",
        );
    }

    public static function orderNotFulfillable(
        int $orderId,
        string $status,
    ): self {
        return new self(
            "Renewal Order [{$orderId}] cannot be fulfilled while its status is [{$status}].",
        );
    }

    public static function orderServerMismatch(
        int $orderId,
        int $serverId,
    ): self {
        return new self(
            "Renewal Order [{$orderId}] is not correlated with Cloud Server [{$serverId}].",
        );
    }

    public static function invalidDuration(int $orderId): self
    {
        return new self(
            "Renewal Order [{$orderId}] has an invalid duration.",
        );
    }
}
