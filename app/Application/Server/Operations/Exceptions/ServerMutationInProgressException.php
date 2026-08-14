<?php

declare(strict_types=1);

namespace App\Application\Server\Operations\Exceptions;

use RuntimeException;

final class ServerMutationInProgressException extends RuntimeException {}
