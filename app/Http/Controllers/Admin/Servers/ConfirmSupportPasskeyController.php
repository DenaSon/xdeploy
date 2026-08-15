<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Servers;

use App\Application\Server\Actions\RecordSupportAccessAction;
use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\Server;
use App\Models\User;
use App\Support\Admin\AdminSupportAccessSession;
use App\Support\Admin\PendingSupportPasskeyVerification;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\PublicKeyCredentialRequestOptions;

final readonly class ConfirmSupportPasskeyController
{
    public function __invoke(
        PasskeyVerificationRequest $request,
        Server $adminServer,
        VerifyPasskey $verifyPasskey,
        PendingSupportPasskeyVerification $pendingVerification,
        AdminSupportAccessSession $supportAccessSession,
        RecordSupportAccessAction $recordSupportAccess,
    ): JsonResponse {
        $admin = $request->user();

        abort_unless(
            $admin instanceof User
            && $admin->isAdmin(),
            403,
        );

        $state = $pendingVerification->consume(
            admin: $admin,
            server: $adminServer,
        );

        abort_unless(
            is_array($state),
            419,
            'فرایند تأیید منقضی شده است. دوباره تلاش کنید.',
        );

        $reason = $state['reason'];
        $options = WebAuthn::fromJson(
            $state['options'],
            PublicKeyCredentialRequestOptions::class,
        );

        try {
            $verifyPasskey(
                $request->credential(),
                $options,
                $admin,
            );
        } catch (InvalidPasskeyException $exception) {
            $recordSupportAccess->handle(
                admin: $admin,
                server: $adminServer,
                action: SupportAccessAction::PasskeyConfirmed,
                reason: $reason,
                successful: false,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );

            throw $exception;
        }

        $supportAccessSession->grant(
            admin: $admin,
            server: $adminServer,
            reason: $reason,
        );

        $recordSupportAccess->handle(
            admin: $admin,
            server: $adminServer,
            action: SupportAccessAction::PasskeyConfirmed,
            reason: $reason,
            successful: true,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()
            ->json([
                'confirmed' => true,
                'expires_in' => 300,
            ])
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }
}
