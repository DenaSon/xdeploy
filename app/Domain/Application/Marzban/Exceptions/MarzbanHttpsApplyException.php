<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Exceptions;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsRecoveryResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use RuntimeException;

final class MarzbanHttpsApplyException extends RuntimeException
{
    private function __construct(
        public readonly MarzbanHttpsApplyFailure $failure,
        public readonly ?MarzbanHttpsRecoveryResult $recovery = null,
    ) {
        parent::__construct($failure->value);
    }

    public static function existingConfiguration(): self
    {
        return new self(
            MarzbanHttpsApplyFailure::ExistingConfiguration,
        );
    }

    public static function operationInProgress(): self
    {
        return new self(
            MarzbanHttpsApplyFailure::OperationInProgress,
        );
    }

    public static function environmentUnavailable(): self
    {
        return new self(
            MarzbanHttpsApplyFailure::EnvironmentUnavailable,
        );
    }

    public static function candidateValidationFailed(): self
    {
        return new self(
            MarzbanHttpsApplyFailure::CandidateValidation,
        );
    }

    public static function mutationFailed(
        ?MarzbanHttpsRecoveryResult $recovery = null,
    ): self {
        return new self(
            failure: MarzbanHttpsApplyFailure::Mutation,
            recovery: $recovery,
        );
    }

    public static function verificationFailed(
        MarzbanHttpsRecoveryResult $recovery,
    ): self {
        return new self(
            failure: MarzbanHttpsApplyFailure::Verification,
            recovery: $recovery,
        );
    }

    public function recoveryAttempted(): bool
    {
        return $this->recovery !== null;
    }

    public function recovered(): bool
    {
        return $this->recovery?->successful() === true;
    }
}
