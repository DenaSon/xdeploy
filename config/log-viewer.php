<?php

declare(strict_types=1);

use Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer;
use Opcodes\LogViewer\Http\Middleware\EnsureFrontendRequestsAreStateful;

return [
    'enabled' => env('LOG_VIEWER_ENABLED', true),
    'api_only' => false,
    'require_auth_in_production' => true,

    'route_domain' => null,
    'route_path' => 'admin/logs',

    'back_to_system_url' => '/admin',
    'back_to_system_label' => 'بازگشت به مدیریت',

    'middleware' => [
        'web',
        'auth',
        'admin',
        'admin.passkey',
        AuthorizeLogViewer::class,
    ],

    'api_middleware' => [
        EnsureFrontendRequestsAreStateful::class,
        'auth',
        'admin',
        'admin.passkey',
        AuthorizeLogViewer::class,
    ],

    // Keep the viewer scoped to Laravel application logs. System logs can be
    // added later with explicit filesystem permissions and operational need.
    'include_files' => [
        '*.log',
        '**/*.log',
    ],
    'exclude_files' => [],
    'hide_unknown_files' => true,

    // Reuse Coreflare's configured cache store for Log Viewer indices unless
    // an operationally separate store is explicitly selected.
    'cache_driver' => env('LOG_VIEWER_CACHE_DRIVER', null),
];
