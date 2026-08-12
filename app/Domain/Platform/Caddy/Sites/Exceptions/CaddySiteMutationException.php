<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites\Exceptions;

use App\Domain\Platform\Caddy\Sites\Enums\CaddySiteMutationFailure;
use RuntimeException;

final class CaddySiteMutationException extends RuntimeException
{
    public function __construct(
        public readonly CaddySiteMutationFailure $failure,
        private readonly bool $configurationRestored = false,
        private readonly bool $serviceRecovered = false,
    ) {
        parent::__construct(
            self::messageFor($failure),
        );
    }

    public function recoveryAttempted(): bool
    {
        return $this->failure === CaddySiteMutationFailure::Mutation
            || $this->failure === CaddySiteMutationFailure::Reload
            || $this->failure === CaddySiteMutationFailure::Recovery;
    }

    public function recovered(): bool
    {
        return $this->configurationRestored
            && $this->serviceRecovered;
    }

    public function configurationRestored(): bool
    {
        return $this->configurationRestored;
    }

    public function serviceRecovered(): bool
    {
        return $this->serviceRecovered;
    }

    private static function messageFor(
        CaddySiteMutationFailure $failure,
    ): string {
        return match ($failure) {
            CaddySiteMutationFailure::Environment => 'The xDeploy-managed Caddy environment is not ready.',
            CaddySiteMutationFailure::CandidateValidation => 'The candidate Caddy site configuration is invalid.',
            CaddySiteMutationFailure::Mutation => 'The Caddy site configuration could not be changed.',
            CaddySiteMutationFailure::Reload => 'Caddy could not reload the new site configuration.',
            CaddySiteMutationFailure::Recovery => 'The Caddy site mutation failed and recovery did not complete.',
            CaddySiteMutationFailure::Busy => 'Another Caddy site mutation is already in progress.',
        };
    }
}
