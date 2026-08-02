<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\Enums;

enum MarzbanHttpsApplyFailure: string
{
    case ExistingConfiguration = 'existing_configuration';
    case OperationInProgress = 'operation_in_progress';
    case EnvironmentUnavailable = 'environment_unavailable';
    case CandidateValidation = 'candidate_validation';
    case Mutation = 'mutation';
    case Verification = 'verification';
}
