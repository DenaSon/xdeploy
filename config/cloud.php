<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    */

    'default' => env(
        'CLOUD_PROVIDER',
        'arvan',
    ),

    /*
    |--------------------------------------------------------------------------
    | Provisioning Workflow
    |--------------------------------------------------------------------------
    */

    'provisioning' => [
        'max_attempts' => (int) env(
            'CLOUD_PROVISIONING_MAX_ATTEMPTS',
            20,
        ),

        'poll_delay_seconds' => (int) env(
            'CLOUD_PROVISIONING_POLL_DELAY_SECONDS',
            3,
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Discovery
    |--------------------------------------------------------------------------
    */

    'discovery_enabled' => filter_var(
        env(
            'ARVAN_CLOUD_DISCOVERY_ENABLED',
            false,
        ),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'arvan' => [
            'base_url' => env(
                'ARVAN_CLOUD_BASE_URL',
                'https://napi.arvancloud.ir/ecc/v1',
            ),

            /*
             * Environment contains only the token.
             * ArvanCloudClient adds the "Apikey" prefix.
             */
            'api_key' => env(
                'ARVAN_CLOUD_API_KEY',
            ),

            'region' => env(
                'ARVAN_CLOUD_REGION',
                'eu-west1-a',
            ),

            'timeouts' => [
                'connect' => (int) env(
                    'ARVAN_CLOUD_CONNECT_TIMEOUT',
                    10,
                ),

                'request' => (int) env(
                    'ARVAN_CLOUD_TIMEOUT',
                    90,
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Provisioning Defaults
            |--------------------------------------------------------------------------
            */

            'defaults' => [
                'size_id' => env(
                    'ARVAN_CLOUD_DEFAULT_SIZE_ID',
                    'eco-1-1-0',
                ),

                'image_id' => env(
                    'ARVAN_CLOUD_DEFAULT_IMAGE_ID',
                    '00aaa9d1-3e0a-468c-aaf4-334513981e42',
                ),

                'network_id' => env(
                    'ARVAN_CLOUD_DEFAULT_NETWORK_ID',
                    'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',
                ),

                /*
                 * ArvanCloud expects the UUID inside
                 * security_groups[].name.
                 */
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

                'username' => env(
                    'ARVAN_CLOUD_DEFAULT_USERNAME',
                    'ubuntu',
                ),

                'disk_size' => (int) env(
                    'ARVAN_CLOUD_DEFAULT_DISK_SIZE',
                    25,
                ),

                'init_script' => env(
                    'ARVAN_CLOUD_DEFAULT_INIT_SCRIPT',
                    '',
                ),

                'ha_enabled' => filter_var(
                    env(
                        'ARVAN_CLOUD_DEFAULT_HA_ENABLED',
                        false,
                    ),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
        ],
    ],
];
