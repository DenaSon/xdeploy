<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudProviderHealthFailureCategory: string
{
    case Authentication = 'authentication';
    case Authorization = 'authorization';
    case Configuration = 'configuration';
    case Connection = 'connection';
    case InsufficientBalance = 'insufficient_balance';
    case NotFound = 'not_found';
    case ProviderServerError = 'provider_server_error';
    case RateLimit = 'rate_limit';
    case Timeout = 'timeout';
    case UnexpectedResponse = 'unexpected_response';
    case UnexpectedStatus = 'unexpected_status';
    case Validation = 'validation';

    public function isAvailabilityFailure(): bool
    {
        return in_array(
            $this,
            [
                self::Connection,
                self::ProviderServerError,
                self::Timeout,
                self::UnexpectedResponse,
                self::UnexpectedStatus,
            ],
            true,
        );
    }

    public function isDegradedSignal(): bool
    {
        return $this === self::RateLimit;
    }
}
