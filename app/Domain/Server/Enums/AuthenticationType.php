<?php

declare(strict_types=1);

namespace App\Domain\Server\Enums;

enum AuthenticationType: string
{
    case Password = 'password';

    case SSHKey = 'ssh_key';

    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Password => 'Password',
            self::SSHKey => 'SSH Key',
            self::Agent => 'SSH Agent',
        };
    }

    public function isSupported(): bool
    {
        return $this === self::Password;
    }

    /**
     * @return list<self>
     */
    public static function supportedCases(): array
    {
        return array_values(
            array_filter(
                self::cases(),
                static fn (self $type): bool => $type->isSupported(),
            ),
        );
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => [
                'id' => $type->value,
                'label' => $type->label(),
            ],
            self::supportedCases(),
        );
    }
}
