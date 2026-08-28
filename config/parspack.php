<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(
        env('PARSPACK_CLOUD_ENABLED', false),
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE,
    ),

    'purchase_enabled' => filter_var(
        env('PARSPACK_CLOUD_PURCHASE_ENABLED', false),
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE,
    ),

    'base_url' => env(
        'PARSPACK_CLOUD_BASE_URL',
        'https://my.parspack.com/cserver/api/public/v1',
    ),

    'api_token' => env('PARSPACK_CLOUD_API_TOKEN'),

    'region' => env(
        'PARSPACK_CLOUD_REGION',
        'frankfurt',
    ),

    'timeouts' => [
        'connect' => (int) env(
            'PARSPACK_CLOUD_CONNECT_TIMEOUT',
            10,
        ),
        'request' => (int) env(
            'PARSPACK_CLOUD_TIMEOUT',
            90,
        ),
    ],

    /*
     * ParsPack creation has been observed to take longer than the generic
     * provider window. Keep this bounded override provider-specific so the
     * existing Arvan/Liara polling behavior remains unchanged.
     */
    'provisioning' => [
        'max_attempts' => (int) env(
            'PARSPACK_CLOUD_PROVISIONING_MAX_ATTEMPTS',
            60,
        ),
        'poll_delay_seconds' => (int) env(
            'PARSPACK_CLOUD_PROVISIONING_POLL_DELAY_SECONDS',
            3,
        ),
    ],

    /*
     * ParsPack API tariffs are normalized as raw IRR by the response mapper.
     * Funding overhead models wallet-funding tax/cost separately and is only
     * applied to the commercial purchase catalog.
     */
    'funding_overhead_percent' => (int) env(
        'PARSPACK_CLOUD_FUNDING_OVERHEAD_PERCENT',
        10,
    ),

    'bootstrap' => [
        /* Account SSH key identifier sent in the create-VM request. */
        'ssh_key_id' => (int) env(
            'PARSPACK_CLOUD_BOOTSTRAP_SSH_KEY_ID',
            0,
        ),

        /*
         * Single-line base64 representation of the matching private key.
         * Never commit the decoded private key to configuration or source.
         */
        'private_key_base64' => env(
            'PARSPACK_CLOUD_BOOTSTRAP_PRIVATE_KEY_BASE64',
        ),
    ],

    'defaults' => [
        'size_id' => env(
            'PARSPACK_CLOUD_DEFAULT_SIZE_ID',
            'deVPS2',
        ),
        'image_id' => env(
            'PARSPACK_CLOUD_DEFAULT_IMAGE_ID',
            'ubuntu24-cloudinit-qcow2',
        ),
        'username' => env(
            'PARSPACK_CLOUD_DEFAULT_USERNAME',
            'root',
        ),
        'disk_size' => (int) env(
            'PARSPACK_CLOUD_DEFAULT_DISK_SIZE',
            40,
        ),
        'init_script' => env(
            'PARSPACK_CLOUD_DEFAULT_INIT_SCRIPT',
            '',
        ),
        'ha_enabled' => filter_var(
            env('PARSPACK_CLOUD_DEFAULT_HA_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),
    ],
];
