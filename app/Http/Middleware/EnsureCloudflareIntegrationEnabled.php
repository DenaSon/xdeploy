<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCloudflareIntegrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            config('services.cloudflare_oauth.enabled', false) === true,
            404,
        );

        return $next($request);
    }
}
