<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Exceptions;

use RuntimeException;

final class SSHPasswordRotationException extends RuntimeException {}
