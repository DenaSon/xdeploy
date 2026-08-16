<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

/**
 * Marks providers whose initial password is a bootstrap credential that
 * Coreflare must rotate after SSH becomes reachable.
 */
interface CloudServerBootstrapCredentialRotationInterface
{
}
