<?php

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudInsufficientBalanceException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Http\Middleware\EnsureAdminPasskeyVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.passkey' => EnsureAdminPasskeyVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * These provider HTTP failures are already captured by the shared
         * CloudProviderHttpObserver with provider, endpoint, status,
         * duration, correlation and error-category context. Reporting them
         * again here would only create a duplicate stacktrace entry.
         *
         * Configuration, local validation and unexpected response failures
         * deliberately remain reportable because they may occur before or
         * after the HTTP observer can record a provider failure.
         */
        $exceptions->dontReport([
            CloudAuthenticationException::class,
            CloudAuthorizationException::class,
            CloudConnectionException::class,
            CloudInsufficientBalanceException::class,
            CloudRateLimitException::class,
            CloudResourceNotFoundException::class,
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
