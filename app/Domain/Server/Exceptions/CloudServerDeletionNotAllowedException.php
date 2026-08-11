<?php

declare(strict_types=1);

namespace App\Domain\Server\Exceptions;

use RuntimeException;

final class CloudServerDeletionNotAllowedException extends RuntimeException {}
