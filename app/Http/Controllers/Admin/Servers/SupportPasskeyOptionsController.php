<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Servers;

use App\Models\Server;
use App\Models\User;
use App\Support\Admin\PendingSupportPasskeyVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Support\WebAuthn;

final readonly class SupportPasskeyOptionsController
{
    public function __invoke(
        Request $request,
        Server $adminServer,
        GenerateVerificationOptions $generate,
        PendingSupportPasskeyVerification $pendingVerification,
    ): JsonResponse {
        $admin = $request->user();

        abort_unless(
            $admin instanceof User
            && $admin->isAdmin(),
            403,
        );

        abort_unless(
            $pendingVerification->isPrepared(
                admin: $admin,
                server: $adminServer,
            ),
            409,
            'ابتدا دلیل دسترسی را ثبت کنید.',
        );

        $options = $generate($admin);
        $serialized = WebAuthn::toJson($options);

        abort_unless(
            $pendingVerification->attachOptions(
                admin: $admin,
                server: $adminServer,
                serializedOptions: $serialized,
            ),
            419,
            'فرایند تأیید منقضی شده است.',
        );

        return response()
            ->json([
                'options' => WebAuthn::toBrowserArray($options),
            ])
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0',
                'Referrer-Policy' => 'no-referrer',
            ]);
    }
}
