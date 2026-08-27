<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers\Termination;

enum CloudServerTerminationState: string
{
    case ReadyForDelete = 'ready_for_delete';
    case PowerOffRequested = 'power_off_requested';
    case WaitingForPowerState = 'waiting_for_power_state';
    case WaitingForExpirationGrace = 'waiting_for_expiration_grace';
    case WaitingForProviderCreatedAt = 'waiting_for_provider_created_at';
    case WaitingForProviderMinimumAge = 'waiting_for_provider_minimum_age';

    public function isReadyForDelete(): bool
    {
        return $this === self::ReadyForDelete;
    }

    public function isWaiting(): bool
    {
        return ! $this->isReadyForDelete();
    }
}
