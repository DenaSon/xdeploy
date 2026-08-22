<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'fake'),
        'otp_browser_console_debug' => filter_var(
            env('OTP_BROWSER_CONSOLE_DEBUG', false),
            FILTER_VALIDATE_BOOL,
        ),
    ],

    'smsir' => [
        'template_id' => (int) env('SMSIR_TEMPLATE_ID'),
        'parameter_name' => env('SMSIR_PARAMETER_NAME', 'Code'),
        'expiring_soon_template_id' => (int) env('SMSIR_EXPIRING_SOON_TEMPLATE_ID'),
        'expiring_soon_parameter_name' => env('SMSIR_EXPIRING_SOON_PARAMETER_NAME', 'Hours'),
    ],

    'google_oidc' => [
        'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'authorization_endpoint' => env(
            'GOOGLE_OAUTH_AUTHORIZATION_ENDPOINT',
            'https://accounts.google.com/o/oauth2/v2/auth',
        ),
        'token_endpoint' => env(
            'GOOGLE_OAUTH_TOKEN_ENDPOINT',
            'https://oauth2.googleapis.com/token',
        ),
        'userinfo_endpoint' => env(
            'GOOGLE_OAUTH_USERINFO_ENDPOINT',
            'https://openidconnect.googleapis.com/v1/userinfo',
        ),
        'connect_timeout' => (int) env(
            'GOOGLE_OAUTH_CONNECT_TIMEOUT',
            5,
        ),
        'timeout' => (int) env(
            'GOOGLE_OAUTH_TIMEOUT',
            10,
        ),
    ],

    'cloudflare_oauth' => [
        'client_id' => env('CLOUDFLARE_OAUTH_CLIENT_ID'),
        'client_secret' => env('CLOUDFLARE_OAUTH_CLIENT_SECRET'),
        'authorization_endpoint' => env(
            'CLOUDFLARE_OAUTH_AUTHORIZATION_ENDPOINT',
            'https://dash.cloudflare.com/oauth2/auth',
        ),
        'token_endpoint' => env(
            'CLOUDFLARE_OAUTH_TOKEN_ENDPOINT',
            'https://dash.cloudflare.com/oauth2/token',
        ),
        'revoke_endpoint' => env(
            'CLOUDFLARE_OAUTH_REVOKE_ENDPOINT',
            'https://dash.cloudflare.com/oauth2/revoke',
        ),
        'scopes' => array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        (string) env(
                            'CLOUDFLARE_OAUTH_SCOPES',
                            'account-settings.read,zone.read,zone.write,dns.read,dns.write,offline_access',
                        ),
                    ),
                ),
            ),
        ),
        'connect_timeout' => (int) env(
            'CLOUDFLARE_OAUTH_CONNECT_TIMEOUT',
            5,
        ),
        'timeout' => (int) env(
            'CLOUDFLARE_OAUTH_TIMEOUT',
            10,
        ),
    ],

    'cloudflare_api' => [
        'base_url' => env(
            'CLOUDFLARE_API_BASE_URL',
            'https://api.cloudflare.com/client/v4',
        ),
        'connect_timeout' => (int) env(
            'CLOUDFLARE_API_CONNECT_TIMEOUT',
            5,
        ),
        'timeout' => (int) env(
            'CLOUDFLARE_API_TIMEOUT',
            15,
        ),
        'max_pages' => (int) env(
            'CLOUDFLARE_API_MAX_PAGES',
            20,
        ),
    ],

    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'link_ttl_seconds' => (int) env(
            'TELEGRAM_LINK_TTL_SECONDS',
            600,
        ),
        'api_base_url' => env(
            'TELEGRAM_API_BASE_URL',
            'https://api.telegram.org',
        ),
        'connect_timeout' => (int) env(
            'TELEGRAM_CONNECT_TIMEOUT',
            5,
        ),
        'timeout' => (int) env(
            'TELEGRAM_TIMEOUT',
            10,
        ),
    ],

];
