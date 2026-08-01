<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\DTOs;

use InvalidArgumentException;

final readonly class CreateMarzbanAdminData
{
    public string $username;

    public string $password;

    public function __construct(
        string $username,
        string $password,
    ) {
        $username = strtolower(trim($username));

        if (
            preg_match(
                '/\A[a-z0-9_]{3,32}\z/',
                $username,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'The admin username must contain 3 to 32 lowercase letters, numbers, or underscores.',
            );
        }

        if (
            strlen($password) < 8
            || strlen($password) > 128
        ) {
            throw new InvalidArgumentException(
                'The admin password must contain 8 to 128 characters.',
            );
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $password,
            ) === 1
        ) {
            throw new InvalidArgumentException(
                'The admin password must not contain control characters.',
            );
        }

        $this->username = $username;
        $this->password = $password;
    }
}
