<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Models\User;

final readonly class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByPhone(
        PhoneNumber $phone,
    ): ?User {
        return User::query()
            ->where('phone', (string) $phone)
            ->first();
    }

    public function create(
        array $attributes,
    ): User {
        return User::create($attributes);
    }

    public function save(
        User $user,
    ): void {
        $user->save();
    }
}
