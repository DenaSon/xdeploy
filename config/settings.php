<?php

use App\Settings\BrandingSettings;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

return [
    'settings' => [
        GeneralSettings::class,
        BrandingSettings::class,
        SeoSettings::class,
    ],

    'setting_class_path' => app_path('Settings'),

    'migrations_paths' => [
        database_path('settings'),
    ],

    'default_repository' => 'database',

    'repositories' => [
        'database' => [
            'type' => DatabaseSettingsRepository::class,
            'model' => null,
            'table' => 'settings',
            'connection' => null,
        ],
    ],

    'encoder' => null,
    'decoder' => null,

    'cache' => [
        'enabled' => (bool) env('SETTINGS_CACHE_ENABLED', true),
        'store' => null,
        'prefix' => 'coreflare.settings',
        'ttl' => null,
        'memo' => (bool) env('SETTINGS_CACHE_MEMO', true),
    ],

    'global_casts' => [
        DateTimeInterface::class => DateTimeInterfaceCast::class,
        DateTimeZone::class => DateTimeZoneCast::class,
    ],

    'auto_discover_settings' => [],

    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];
