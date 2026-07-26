<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use App\Application\User\Actions\FindOrCreateUserAction;
use App\Domain\Authentication\DTOs\VerifyOtpData;
use App\Domain\Authentication\Services\OtpService;
use Laravel\Sanctum\NewAccessToken;

final readonly class VerifyOtpAction
{
    public function __construct(
        private OtpService $otpService,
        private FindOrCreateUserAction $findOrCreateUser,
        private LoginAction $loginAction,
    ) {}

    public function handle(
        VerifyOtpData $data,
    ): NewAccessToken {
        $this->otpService->validate(
            phone: $data->phone,
            code: $data->code,
        );

        $user = $this->findOrCreateUser->handle(
            $data->phone,
        );

        return $this->loginAction->handle(
            $user,
        );
    }
}
