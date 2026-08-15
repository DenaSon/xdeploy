<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Admin\AdminPasskeyVerificationSession;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureAdminPasskeyVerified
{
    public function __construct(
        private AdminPasskeyVerificationSession $verificationSession,
    ) {}

    public function handle(
        Request $request,
        Closure $next,
    ): Response|RedirectResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->isAdmin(),
            403,
        );

        if (! $user->passkeys()->exists()) {
            $this->verificationSession->revoke();
            $request->session()->put(
                'url.intended',
                $request->fullUrl(),
            );

            return redirect()
                ->route('panel.security')
                ->with(
                    'admin_passkey_required',
                    true,
                );
        }

        if (! $this->verificationSession->isGranted($user)) {
            $request->session()->put(
                'url.intended',
                $request->fullUrl(),
            );

            return redirect()->route(
                'admin.passkey.confirm',
            );
        }

        return $next($request);
    }
}
