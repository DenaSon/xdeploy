<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cloud Provider
    |--------------------------------------------------------------------------
    */

    'default' => env(
        'CLOUD_PROVIDER',
        'arvan',
    ),

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    |
    | Temporary local-only cloud discovery routes.
    | This must remain disabled in production.
    |
    */

    'discovery_enabled' => env(
        'ARVAN_CLOUD_DISCOVERY_ENABLED',
        false,
    ),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'arvan' => [
            /*
            |--------------------------------------------------------------------------
            | Connection
            |--------------------------------------------------------------------------
            */

            'base_url' => env(
                'ARVAN_CLOUD_BASE_URL',
                'https://napi.arvancloud.ir/ecc/v1',
            ),

            /*
             * Store only the raw token in the environment.
             * The "Apikey" prefix is added by ArvanCloudClient.
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
                /*
                 * ArvanCloud flavor ID.
                 */
                'size_id' => env(
                    'ARVAN_CLOUD_DEFAULT_SIZE_ID',
                    'eco-2-2-0',
                ),

                /*
                 * Ubuntu 24.04 image ID.
                 */
                'image_id' => env(
                    'ARVAN_CLOUD_DEFAULT_IMAGE_ID',
                    '00aaa9d1-3e0a-468c-aaf4-334513981e42',
                ),

                /*
                 * Default public IPv4 network ID.
                 */
                'network_id' => env(
                    'ARVAN_CLOUD_DEFAULT_NETWORK_ID',
                    'c72ea6b9-e1c1-4b72-80eb-adc6fc1941a2',
                ),

                /*
                 * The create-server API expects the security-group UUID
                 * inside security_groups[].name.
                 */
                'security_group_id' => env(
                    'ARVAN_CLOUD_DEFAULT_SECURITY_GROUP_ID',
                    '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                ),

                /*
                 * Kept for catalog display and configuration compatibility.
                 * Provisioning must use security_group_id.
                 */
                'security_group_name' => env(
                    'ARVAN_CLOUD_DEFAULT_SECURITY_GROUP_NAME',
                    'default',
                ),

                /*
                 * Volume-backed server creation.
                 */
                'create_type' => env(
                    'ARVAN_CLOUD_DEFAULT_CREATE_TYPE',
                    'cinder',
                ),

                /*
                 * Root volume size in GiB.
                 */
                'disk_size' => (int) env(
                    'ARVAN_CLOUD_DEFAULT_DISK_SIZE',
                    30,
                ),

                /*
                 * Optional provider initialization script.
                 */
                'init_script' => env(
                    'ARVAN_CLOUD_DEFAULT_INIT_SCRIPT',
                    '',
                ),

                /*
                 * High availability remains disabled for the MVP.
                 */
                'ha_enabled' => env(
                    'ARVAN_CLOUD_DEFAULT_HA_ENABLED',
                    false,
                ),
            ],
        ],
    ],
];
