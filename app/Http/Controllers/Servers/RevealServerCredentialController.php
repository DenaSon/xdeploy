<?php

declare(strict_types=1);

namespace App\Http\Controllers\Servers;

use App\Domain\Server\Enums\AuthenticationType;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RevealServerCredentialController
{
    public function __invoke(
        Request $request,
        Server $server,
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        $ownedServer = $user
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        abort_unless(
            $ownedServer->authentication_type === AuthenticationType::Password,
            404,
        );

        $credential = $ownedServer->credential;

        abort_unless(
            is_string($credential)
            && $credential !== '',
            404,
        );

        return response()
            ->json([
                'credential' => $credential,
            ])
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
            ]);
    }
}
