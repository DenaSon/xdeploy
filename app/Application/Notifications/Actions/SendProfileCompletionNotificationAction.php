<?php

declare(strict_types=1);

namespace App\Application\Notifications\Actions;

use App\Models\User;
use App\Notifications\Profile\ProfileCompletionRequiredNotification;
use Throwable;

final readonly class SendProfileCompletionNotificationAction
{
    public function __construct(
        private SendNotificationOnceAction $sendOnce,
    ) {}

    public function execute(User $user): void
    {
        $user->loadMissing('profile');

        if (! $this->profileIsIncomplete($user)) {
            return;
        }

        try {
            $this->sendOnce->execute(
                user: $user,
                dedupeKey: sprintf(
                    'profile-completion:%s:v1',
                    $user->getKey(),
                ),
                notification: new ProfileCompletionRequiredNotification,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function profileIsIncomplete(User $user): bool
    {
        return blank($user->profile?->first_name)
            || blank($user->profile?->last_name)
            || blank($user->email);
    }
}
