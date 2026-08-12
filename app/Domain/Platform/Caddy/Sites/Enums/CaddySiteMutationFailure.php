<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites\Enums;

enum CaddySiteMutationFailure: string
{
    case Environment = 'environment';
    case CandidateValidation = 'candidate';
    case Mutation = 'mutation';
    case Reload = 'reload';
    case Recovery = 'recovery';
    case Busy = 'busy';
}
