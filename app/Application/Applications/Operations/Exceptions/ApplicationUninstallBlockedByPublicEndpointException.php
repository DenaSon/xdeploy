<?php

declare(strict_types=1);

namespace App\Application\Applications\Operations\Exceptions;

use RuntimeException;

final class ApplicationUninstallBlockedByPublicEndpointException extends RuntimeException {}
