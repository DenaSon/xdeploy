<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Provider
    |--------------------------------------------------------------------------
    */

    'default' => env('CLOUD_PROVIDER', 'arvan'),

    /*
    |--------------------------------------------------------------------------
    | Cloud Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'arvan' => [
            'base_url' => env(
                'ARVAN_CLOUD_BASE_URL',
                'https://napi.arvancloud.ir/ecc/v1',
            ),

            'api_key' => env('ARVAN_CLOUD_API_KEY'),

            'region' => env(
                'ARVAN_CLOUD_REGION',
                'eu-west1-a',
            ),

            'timeouts' => [
                'connect' => (int) env(
                    'ARVAN_CLOUD_CONNECT_TIMEOUT',
                    5,
                ),

                'request' => (int) env(
                    'ARVAN_CLOUD_TIMEOUT',
                    15,
                ),
            ],

            'defaults' => [
                'size_id' => env(
                    'ARVAN_CLOUD_DEFAULT_SIZE_ID',
                    'eco-2-2-0',
                ),

                'image_id' => env(
                    'ARVAN_CLOUD_DEFAULT_IMAGE_ID',
                    '00aaa9d1-3e0a-468c-aaf4-334513981e42',
                ),

                'network_id' => env(
                    'ARVAN_CLOUD_DEFAULT_NETWORK_ID',
                    'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',
                ),

                'security_group_id' => env(
                    'ARVAN_CLOUD_DEFAULT_SECURITY_GROUP_ID',
                    '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                ),

                'security_group_name' => env(
                    'ARVAN_CLOUD_DEFAULT_SECURITY_GROUP_NAME',
                    'default',
                ),

                'create_type' => env(
                    'ARVAN_CLOUD_DEFAULT_CREATE_TYPE',
                    'cinder',
                ),
            ],
        ],

    ],

];
