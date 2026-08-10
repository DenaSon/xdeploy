<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Admin\DTOs;

final readonly class MarzbanAdminInfo
{
    public function __construct(
        public string $username,
    ) {}

    /**
     * @return array{
     *     username: string
     * }
     */
    public function toArray(): array
    {
        return [
            'username' => $this->username,
        ];
    }
}
