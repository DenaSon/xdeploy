<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Snapshot-capable Cloud Providers
    |--------------------------------------------------------------------------
    |
    | ArvanCloud exposes the tested server snapshot API under ecc/v2,
    | while the rest of the current Cloud integration uses ecc/v1.
    |
    */

    'providers' => [
        'arvan' => [
            'base_url' => env(
                'ARVAN_CLOUD_V2_BASE_URL',
                'https://napi.arvancloud.ir/ecc/v2',
            ),
        ],
    ],
];
