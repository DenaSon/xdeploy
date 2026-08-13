<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint\Operations\Exceptions;

use RuntimeException;

final class PublicEndpointOperationInProgressException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Another public endpoint operation is already in progress for this target.',
        );
    }
}
