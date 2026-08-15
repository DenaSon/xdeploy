<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Models\User;

final readonly class UserService
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {}

    public function findByPhone(
        PhoneNumber $phone,
    ): ?User {
        return $this->users->findByPhone($phone);
    }

    public function create(
        PhoneNumber $phone,
    ): User {
        return $this->users->create([
            'phone' => (string) $phone,
        ]);
    }

    public function firstOrCreate(
        PhoneNumber $phone,
    ): User {
        return $this->findByPhone($phone)
            ?? $this->create($phone);
    }
}
