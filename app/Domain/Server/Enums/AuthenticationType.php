<?php

declare(strict_types=1);

namespace App\Domain\Server\Enums;

enum AuthenticationType: string
{
    /**
     * Authenticate using a username and password.
     */
    case Password = 'password';

    /**
     * Authenticate using a private SSH key.
     */
    case SSHKey = 'ssh_key';

    /**
     * Authenticate using the local SSH agent.
     */
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Password => 'Password',
            self::SSHKey => 'SSH Key',
            self::Agent => 'SSH Agent',
        };
    }

    public static function options(): array
    {
        return array_map(
            static fn (self $type) => [
                'id' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
