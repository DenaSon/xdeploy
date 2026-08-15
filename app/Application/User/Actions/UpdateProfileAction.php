<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Models\User;

final readonly class UpdateProfileAction
{
    public function handle(
        User $user,
        ?string $firstName,
        ?string $lastName,
    ): void {
        $firstName = $this->normalize($firstName);
        $lastName = $this->normalize($lastName);

        if ($firstName === null && $lastName === null) {
            $user->profile()->delete();
            $user->setRelation('profile', null);

            return;
        }

        $profile = $user->profile()->updateOrCreate(
            [],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ],
        );

        $user->setRelation('profile', $profile);
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }
}
