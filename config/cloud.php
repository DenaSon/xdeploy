<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cloud Provider
    |--------------------------------------------------------------------------
    |
    | مشخص می‌کند کدام ارائه‌دهنده Cloud به‌صورت پیش‌فرض توسط xDeploy
    | استفاده شود.
    |
    */

    'default' => env(
        'CLOUD_PROVIDER',
        'arvan',
    ),

    /*
    |--------------------------------------------------------------------------
    | Purchase Catalog Cache
    |--------------------------------------------------------------------------
    |
    | این Cache فقط برای Catalog نمایشی صفحه خرید استفاده می‌شود.
    | مسیر authoritative قیمت، سفارش، پرداخت و Provisioning مستقیم باقی می‌ماند.
    |
    */

    'catalog_cache' => [
        'enabled' => filter_var(
            env(
                'CLOUD_CATALOG_CACHE_ENABLED',
                true,
            ),
            FILTER_VALIDATE_BOOL,
        ),

        'lock_seconds' => (int) env(
            'CLOUD_CATALOG_CACHE_LOCK_SECONDS',
            30,
        ),

        'regions' => [
            'fresh_seconds' => (int) env(
                'CLOUD_CATALOG_REGIONS_FRESH_SECONDS',
                1_800,
            ),

            'stale_seconds' => (int) env(
                'CLOUD_CATALOG_REGIONS_STALE_SECONDS',
                21_600,
            ),
        ],

        'sizes' => [
            'fresh_seconds' => (int) env(
                'CLOUD_CATALOG_SIZES_FRESH_SECONDS',
                600,
            ),

            'stale_seconds' => (int) env(
                'CLOUD_CATALOG_SIZES_STALE_SECONDS',
                3_600,
            ),
        ],

        'images' => [
            'fresh_seconds' => (int) env(
                'CLOUD_CATALOG_IMAGES_FRESH_SECONDS',
                1_800,
            ),

            'stale_seconds' => (int) env(
                'CLOUD_CATALOG_IMAGES_STALE_SECONDS',
                21_600,
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provisioning Workflow
    |--------------------------------------------------------------------------
    |
    | تنظیمات Polling مربوط به ساخت ابرک و انتظار برای آماده‌شدن آن.
    |
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
    | Resize Workflow
    |--------------------------------------------------------------------------
    |
    | تنظیمات مربوط به تغییر اندازه ابرک، انتظار برای خاموش‌شدن،
    | روشن‌شدن خودکار پس از Resize و قفل عملیات هم‌زمان.
    |
    */

    'resize' => [
        'enabled' => filter_var(
            env(
                'CLOUD_RESIZE_ENABLED',
                false,
            ),
            FILTER_VALIDATE_BOOL,
        ),

        'stop_polling' => [
            'attempts' => (int) env(
                'CLOUD_RESIZE_STOP_ATTEMPTS',
                30,
            ),

            'delay_milliseconds' => (int) env(
                'CLOUD_RESIZE_STOP_DELAY_MS',
                5_000,
            ),
        ],

        'active_polling' => [
            'attempts' => (int) env(
                'CLOUD_RESIZE_ACTIVE_ATTEMPTS',
                120,
            ),

            'delay_milliseconds' => (int) env(
                'CLOUD_RESIZE_ACTIVE_DELAY_MS',
                5_000,
            ),
        ],

        'operation_lock' => [
            'ttl_seconds' => (int) env(
                'CLOUD_RESIZE_LOCK_TTL_SECONDS',
                1_200,
            ),

            'wait_seconds' => (int) env(
                'CLOUD_RESIZE_LOCK_WAIT_SECONDS',
                3,
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Cloud Discovery
    |--------------------------------------------------------------------------
    |
    | Routeهای موقت Discovery و آزمایش APIهای واقعی Cloud را فعال می‌کند.
    |
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
    | Cloud Providers
    |--------------------------------------------------------------------------
    |
    | enabled کنترل می‌کند Provider برای مدیریت resourceهای موجود در Registry
    | عملیاتی باشد. purchase_enabled فقط اجازه خرید جدید را کنترل می‌کند.
    | Provider غیرفعال نمی‌تواند برای خرید جدید فعال باشد.
    |
    */

    'providers' => [
        /*
        |--------------------------------------------------------------------------
        | ArvanCloud
        |--------------------------------------------------------------------------
        */

        'arvan' => [
            'enabled' => filter_var(
                env(
                    'ARVAN_CLOUD_ENABLED',
                    true,
                ),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ),

            'purchase_enabled' => filter_var(
                env(
                    'ARVAN_CLOUD_PURCHASE_ENABLED',
                    true,
                ),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ),

            'base_url' => env(
                'ARVAN_CLOUD_BASE_URL',
                'https://napi.arvancloud.ir/ecc/v1',
            ),

            /*
             * مقدار Environment فقط شامل Token است.
             * پیشوند Apikey توسط ArvanCloudClient اضافه می‌شود.
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

            'package_repositories' => [
                'ubuntu_mirror' => env(
                    'ARVAN_CLOUD_UBUNTU_MIRROR',
                    'https://mirror.arvancloud.ir/ubuntu',
                ),
            ],

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

        /*
        |--------------------------------------------------------------------------
        | Liara IaaS
        |--------------------------------------------------------------------------
        |
        | Liara API prices are returned in Tomans. The Liara provider adapter
        | normalizes them to IRR before they enter Coreflare billing logic.
        |
        */

        'liara' => [
            'enabled' => filter_var(
                env(
                    'LIARA_CLOUD_ENABLED',
                    false,
                ),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ),

            'purchase_enabled' => filter_var(
                env(
                    'LIARA_CLOUD_PURCHASE_ENABLED',
                    false,
                ),
                FILTER_VALIDATE_BOOL,
                FILTER_NULL_ON_FAILURE,
            ),

            'base_url' => env(
                'LIARA_CLOUD_BASE_URL',
                'https://iaas-api.liara.ir',
            ),

            /*
             * Environment contains only the JWT/token value.
             * LiaraCloudClient adds the Bearer scheme.
             */
            'api_token' => env(
                'LIARA_CLOUD_API_TOKEN',
            ),

            'region' => env(
                'LIARA_CLOUD_REGION',
                'iran',
            ),

            'timeouts' => [
                'connect' => (int) env(
                    'LIARA_CLOUD_CONNECT_TIMEOUT',
                    10,
                ),

                'request' => (int) env(
                    'LIARA_CLOUD_TIMEOUT',
                    90,
                ),
            ],

            'defaults' => [
                'size_id' => env(
                    'LIARA_CLOUD_DEFAULT_SIZE_ID',
                    'standard-base-g2',
                ),

                'image_id' => env(
                    'LIARA_CLOUD_DEFAULT_IMAGE_ID',
                    'ubuntu-24.04',
                ),

                'username' => env(
                    'LIARA_CLOUD_DEFAULT_USERNAME',
                    'root',
                ),

                'disk_size' => (int) env(
                    'LIARA_CLOUD_DEFAULT_DISK_SIZE',
                    20,
                ),

                'init_script' => env(
                    'LIARA_CLOUD_DEFAULT_INIT_SCRIPT',
                    '',
                ),

                'ha_enabled' => filter_var(
                    env(
                        'LIARA_CLOUD_DEFAULT_HA_ENABLED',
                        false,
                    ),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
        ],
    ],
];