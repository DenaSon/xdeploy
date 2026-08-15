<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->isAdmin(),
            403,
        );

        return $next($request);
    }
}
