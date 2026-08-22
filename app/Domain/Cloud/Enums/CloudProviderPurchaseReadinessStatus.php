<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudProviderPurchaseReadinessStatus: string
{
    case Ready = 'ready';
    case BlockedCredentials = 'blocked_credentials';
    case BlockedConfiguration = 'blocked_configuration';
    case BlockedBalance = 'blocked_balance';
    case TemporarilyUnavailable = 'temporarily_unavailable';

    public function allowsPurchase(): bool
    {
        return $this === self::Ready;
    }
}
