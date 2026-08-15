<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Servers;

use App\Application\Server\Actions\RecordSupportAccessAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\Server;
use App\Models\User;
use App\Support\Admin\AdminSupportAccessSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevealSupportCredentialController
{
    public function __invoke(
        Request $request,
        Server $adminServer,
        AdminSupportAccessSession $supportAccessSession,
        RecordSupportAccessAction $recordSupportAccess,
    ): JsonResponse {
        $admin = $request->user();

        abort_unless(
            $admin instanceof User
            && $admin->isAdmin(),
            403,
        );

        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ]);

        abort_unless(
            $supportAccessSession->isGranted(
                admin: $admin,
                server: $adminServer,
            ),
            403,
        );

        if (
            $adminServer->authentication_type
            !== AuthenticationType::Password
        ) {
            $this->record(
                recordSupportAccess: $recordSupportAccess,
                admin: $admin,
                server: $adminServer,
                reason: $validated['reason'],
                successful: false,
                request: $request,
            );

            abort(404);
        }

        $credential = $adminServer->credential;

        if (! is_string($credential) || $credential === '') {
            $this->record(
                recordSupportAccess: $recordSupportAccess,
                admin: $admin,
                server: $adminServer,
                reason: $validated['reason'],
                successful: false,
                request: $request,
            );

            abort(404);
        }

        $this->record(
            recordSupportAccess: $recordSupportAccess,
            admin: $admin,
            server: $adminServer,
            reason: $validated['reason'],
            successful: true,
            request: $request,
        );

        return response()
            ->json([
                'credential' => $credential,
                'expires_in' => 30,
            ])
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }

    private function record(
        RecordSupportAccessAction $recordSupportAccess,
        User $admin,
        Server $server,
        string $reason,
        bool $successful,
        Request $request,
    ): void {
        $recordSupportAccess->handle(
            admin: $admin,
            server: $server,
            action: SupportAccessAction::CredentialRevealed,
            reason: $reason,
            successful: $successful,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
