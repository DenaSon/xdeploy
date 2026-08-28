<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Server;

use App\Domain\Server\Enums\AuthenticationType;
use PHPUnit\Framework\TestCase;

final class AuthenticationTypeTest extends TestCase
{
    public function test_password_and_ssh_key_are_supported_but_agent_is_not(): void
    {
        self::assertSame(
            [
                AuthenticationType::Password,
                AuthenticationType::SSHKey,
            ],
            AuthenticationType::supportedCases(),
        );

        self::assertSame(
            [
                [
                    'id' => 'password',
                    'label' => 'Password',
                ],
                [
                    'id' => 'ssh_key',
                    'label' => 'SSH Key',
                ],
            ],
            AuthenticationType::options(),
        );

        self::assertFalse(
            AuthenticationType::Agent->isSupported(),
        );
    }
}
