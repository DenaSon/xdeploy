<?php

declare(strict_types=1);

namespace App\Domain\Application\AmneziaWg\Peer\Exceptions;

use RuntimeException;

final class AmneziaWgPeerManagementException extends RuntimeException
{
    public static function creationFailed(): self
    {
        return new self('Unable to create the AmneziaWG peer on the remote server.');
    }

    public static function removalFailed(): self
    {
        return new self('Unable to remove the AmneziaWG peer from the remote server.');
    }

    public static function inspectionFailed(): self
    {
        return new self('Unable to inspect AmneziaWG peer runtime state.');
    }
}
