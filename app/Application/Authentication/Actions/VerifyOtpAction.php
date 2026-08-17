<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use App\Application\Notifications\Actions\SendProfileCompletionNotificationAction;
use App\Application\User\Actions\FindOrCreateUserAction;
use App\Domain\Authentication\DTOs\VerifyOtpData;
use App\Domain\Authentication\Services\OtpService;

final readonly class VerifyOtpAction
{
    public function __construct(
        private OtpService $otpService,
        private FindOrCreateUserAction $findOrCreateUser,
        private LoginAction $login,
        private SendProfileCompletionNotificationAction $profileCompletionNotification,
    ) {}

    public function handle(
        VerifyOtpData $data,
    ): void {
        $this->otpService->validate(
            phone: $data->phone,
            code: $data->code,
        );

        $user = $this->findOrCreateUser->handle(
            $data->phone,
        );

        $this->login->handle(
            $user,
        );

        $this->profileCompletionNotification->execute(
            $user,
        );
    }
}
