<?php

declare(strict_types=1);

namespace App\Http\Responses\Security;

use App\Models\User;
use App\Support\Admin\AdminPasskeyVerificationSession;
use Laravel\Passkeys\Http\Responses\PasskeyLoginResponse as BasePasskeyLoginResponse;

final class PasskeyLoginResponse extends BasePasskeyLoginResponse
{
    public function __construct(
        private readonly AdminPasskeyVerificationSession $verificationSession,
    ) {}

    public function toResponse($request)
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin()) {
            $this->verificationSession->grant($user);
        }

        return parent::toResponse($request);
    }
}
