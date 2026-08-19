<?php

declare(strict_types=1);

namespace App\Http\Controllers\Impersonation;

use App\Models\User;
use App\Support\Admin\AdminImpersonationSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class StopUserImpersonationController
{
    public function __invoke(
        Request $request,
        AdminImpersonationSession $impersonationSession,
    ): RedirectResponse {
        $currentUser = $request->user();

        abort_unless(
            $currentUser instanceof User,
            401,
        );

        $adminId = $impersonationSession->adminIdFor(
            $currentUser,
        );

        abort_unless(
            is_int($adminId),
            403,
        );

        $admin = User::query()->find($adminId);

        if (! $admin instanceof User || ! $admin->isAdmin()) {
            $impersonationSession->clear();

            abort(403);
        }

        $targetUserId = (int) $currentUser->getKey();

        $impersonationSession->clear();

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route(
            'admin.users.show',
            ['user' => $targetUserId],
        );
    }
}
