<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Admin\DTOs;

use App\Domain\Application\Marzban\Setup\Enums\MarzbanSetupState;

final readonly class MarzbanAdminOverview
{
    /**
     * @param  list<MarzbanAdminInfo>  $admins
     */
    public function __construct(
        public MarzbanSetupState $state,
        public array $admins,
    ) {}

    /**
     * @param  list<MarzbanAdminInfo>  $admins
     */
    public static function fromAdmins(
        array $admins,
    ): self {
        return new self(
            state: $admins === []
                ? MarzbanSetupState::Pending
                : MarzbanSetupState::Complete,
            admins: $admins,
        );
    }

    public static function notRequired(): self
    {
        return new self(
            state: MarzbanSetupState::NotRequired,
            admins: [],
        );
    }

    public static function unknown(): self
    {
        return new self(
            state: MarzbanSetupState::Unknown,
            admins: [],
        );
    }

    public function hasAdmin(
        string $username,
    ): bool {
        foreach ($this->admins as $admin) {
            if ($admin->username === $username) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     state: string,
     *     admins: list<array{
     *         username: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'admins' => array_map(
                static fn (
                    MarzbanAdminInfo $admin,
                ): array => $admin->toArray(),
                $this->admins,
            ),
        ];
    }
}
