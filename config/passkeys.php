<?php

declare(strict_types=1);

$configuredOrigins = env('PASSKEYS_ALLOWED_ORIGINS');

$allowedOrigins = is_string($configuredOrigins)
    && trim($configuredOrigins) !== ''
        ? array_values(array_filter(
            array_map('trim', explode(',', $configuredOrigins)),
        ))
        : [config('app.url')];

return [
    'relying_party_id' => env('PASSKEYS_RELYING_PARTY_ID')
        ?: parse_url((string) config('app.url'), PHP_URL_HOST),

    'allowed_origins' => $allowedOrigins,

    'user_handle_secret' => env(
        'PASSKEYS_USER_HANDLE_SECRET',
        config('app.key'),
    ),

    'timeout' => 60_000,

    'guard' => 'web',

    'middleware' => ['web'],

    /*
     * Coreflare is passwordless. Passkey management is already protected by
     * authenticated Panel routes, so Laravel's password.confirm middleware
     * is intentionally not applicable here.
     */
    'management_middleware' => [],

    'throttle' => 'throttle:6,1',

    'redirect' => '/panel/servers',

    'admin_verification_seconds' => (int) env(
        'PASSKEYS_ADMIN_VERIFICATION_SECONDS',
        3_600,
    ),
];
