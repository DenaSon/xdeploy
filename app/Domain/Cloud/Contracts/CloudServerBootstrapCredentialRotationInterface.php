<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

/**
 * Marks providers whose initial SSH credential is bootstrap-only and must be
 * replaced by a Coreflare-managed password after SSH becomes reachable.
 * The initial credential may itself be a password or an SSH private key.
 */
interface CloudServerBootstrapCredentialRotationInterface {}
