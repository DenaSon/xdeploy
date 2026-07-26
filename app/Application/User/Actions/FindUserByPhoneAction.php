<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Domain\User\Services\UserService;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Models\User;

final readonly class FindUserByPhoneAction
{
    public function __construct(
        private UserService $users,
    ) {}

    public function handle(
        PhoneNumber $phone,
    ): ?User {
        return $this->users->findByPhone($phone);
    }
}
