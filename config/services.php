<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'fake'),
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

];
