<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Domain\User\Services\UserService;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Models\User;

final readonly class CreateUserAction
{
    public function __construct(
        private UserService $users,
    ) {}

    public function handle(
        PhoneNumber $phone,
        ?string $name = null,
    ): User {
        return $this->users->create(
            phone: $phone,
            name: $name,
        );
    }
}
