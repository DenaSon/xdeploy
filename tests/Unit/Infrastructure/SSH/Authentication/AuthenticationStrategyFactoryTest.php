<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\SSH\Authentication;

use App\Domain\Server\Enums\AuthenticationType;
use App\Infrastructure\SSH\Authentication\AuthenticationStrategyFactory;
use App\Infrastructure\SSH\Authentication\PasswordAuthenticator;
use App\Infrastructure\SSH\Authentication\SSHKeyAuthenticator;
use App\Infrastructure\SSH\Exceptions\UnsupportedAuthenticationTypeException;
use PHPUnit\Framework\TestCase;

final class AuthenticationStrategyFactoryTest extends TestCase
{
    public function test_password_strategy_is_resolved(): void
    {
        self::assertInstanceOf(
            PasswordAuthenticator::class,
            (new AuthenticationStrategyFactory)->make(
                AuthenticationType::Password,
            ),
        );
    }

    public function test_ssh_key_strategy_is_resolved_for_internal_runtime_use(): void
    {
        self::assertInstanceOf(
            SSHKeyAuthenticator::class,
            (new AuthenticationStrategyFactory)->make(
                AuthenticationType::SSHKey,
            ),
        );
    }

    public function test_agent_authentication_remains_unsupported(): void
    {
        $this->expectException(
            UnsupportedAuthenticationTypeException::class,
        );

        (new AuthenticationStrategyFactory)->make(
            AuthenticationType::Agent,
        );
    }
}
