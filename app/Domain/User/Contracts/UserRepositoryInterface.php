<?php

declare(strict_types=1);

namespace App\Domain\User\Contracts;

use App\Domain\User\ValueObjects\PhoneNumber;
use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByPhone(
        PhoneNumber $phone,
    ): ?User;

    public function create(
        array $attributes,
    ): User;

    public function save(
        User $user,
    ): void;
}
