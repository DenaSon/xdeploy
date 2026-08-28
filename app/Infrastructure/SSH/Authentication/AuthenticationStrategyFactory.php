<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Authentication;

use App\Domain\Server\Enums\AuthenticationType;
use App\Infrastructure\SSH\Exceptions\UnsupportedAuthenticationTypeException;

final class AuthenticationStrategyFactory
{
    public function make(
        AuthenticationType $type,
    ): AuthenticationStrategy {
        return match ($type) {
            AuthenticationType::Password => new PasswordAuthenticator,
            AuthenticationType::SSHKey => new SSHKeyAuthenticator,
            AuthenticationType::Agent => throw UnsupportedAuthenticationTypeException::forType($type),
        };
    }
}
