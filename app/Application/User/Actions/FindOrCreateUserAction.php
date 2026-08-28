<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Application\User\Events\UserRegistered;
use App\Domain\User\Services\UserService;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Event;

final readonly class FindOrCreateUserAction
{
    public function __construct(
        private UserService $users,
    ) {}

    public function handle(
        PhoneNumber $phone,
    ): User {
        $user = $this->users->findByPhone(
            $phone,
        );

        if ($user instanceof User) {
            return $user;
        }

        $user = $this->users->create(
            $phone,
        );

        Event::dispatch(new UserRegistered(
            userId: (int) $user->getKey(),
        ));

        return $user;
    }
}
