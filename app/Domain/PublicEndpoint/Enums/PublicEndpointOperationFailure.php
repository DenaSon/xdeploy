<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\Enums;

enum PublicEndpointOperationFailure: string
{
    case Preflight = 'preflight';
    case ExistingConfiguration = 'existing_configuration';
    case OperationInProgress = 'operation_in_progress';
    case EnvironmentUnavailable = 'environment_unavailable';
    case CandidateValidation = 'candidate_validation';
    case Mutation = 'mutation';
    case Verification = 'verification';
}
