<?php

namespace App\Infrastructure\SSH\Authentication;

use App\Domain\Server\Enums\AuthenticationType;

class AuthenticationStrategyFactory
{
    public function make(
        AuthenticationType $type,
    ): AuthenticationStrategy {
        return match ($type) {

            AuthenticationType::Password => new PasswordAuthenticator,

            AuthenticationType::SSHKey => new SSHKeyAuthenticator,

            AuthenticationType::Agent => new AgentAuthenticator,
        };
    }
}
