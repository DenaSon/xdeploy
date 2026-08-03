<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Server credential encryption
    |--------------------------------------------------------------------------
    */

    'server_credentials' => [

        'current_key_id' => env(
            'SERVER_CREDENTIALS_CURRENT_KEY_ID',
            'v1',
        ),

        'keys' => [

            'v1' => env(
                'SERVER_CREDENTIALS_KEY_V1',
            ),

        ],

    ],

];
