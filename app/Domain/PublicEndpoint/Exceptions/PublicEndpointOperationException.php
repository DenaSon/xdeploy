<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\Exceptions;

use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use RuntimeException;

final class PublicEndpointOperationException extends RuntimeException
{
    private function __construct(
        public readonly PublicEndpointOperationFailure $failure,
        private readonly bool $recoveryAttempted = false,
        private readonly bool $recovered = false,
    ) {
        parent::__construct($failure->value);
    }

    public static function preflightFailed(): self
    {
        return new self(PublicEndpointOperationFailure::Preflight);
    }

    public static function existingConfiguration(): self
    {
        return new self(PublicEndpointOperationFailure::ExistingConfiguration);
    }

    public static function operationInProgress(): self
    {
        return new self(PublicEndpointOperationFailure::OperationInProgress);
    }

    public static function environmentUnavailable(): self
    {
        return new self(PublicEndpointOperationFailure::EnvironmentUnavailable);
    }

    public static function candidateValidationFailed(): self
    {
        return new self(PublicEndpointOperationFailure::CandidateValidation);
    }

    public static function mutationFailed(
        bool $recoveryAttempted = false,
        bool $recovered = false,
    ): self {
        return new self(
            failure: PublicEndpointOperationFailure::Mutation,
            recoveryAttempted: $recoveryAttempted,
            recovered: $recovered,
        );
    }

    public static function verificationFailed(
        bool $recovered,
    ): self {
        return new self(
            failure: PublicEndpointOperationFailure::Verification,
            recoveryAttempted: true,
            recovered: $recovered,
        );
    }

    public function recoveryAttempted(): bool
    {
        return $this->recoveryAttempted;
    }

    public function recovered(): bool
    {
        return $this->recovered;
    }
}
