<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Users;

use App\Models\User;
use App\Support\Admin\AdminImpersonationSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class StartUserImpersonationController
{
    public function __invoke(
        Request $request,
        User $user,
        AdminImpersonationSession $impersonationSession,
    ): RedirectResponse {
        $admin = $request->user();

        abort_unless(
            $admin instanceof User
            && $admin->isAdmin(),
            403,
        );

        abort_if(
            $admin->is($user)
            || $user->isAdmin(),
            403,
        );

        $impersonationSession->start(
            admin: $admin,
            target: $user,
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel.servers.index');
    }
}
