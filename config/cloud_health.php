<?php

declare(strict_types=1);

return [
    'state_ttl_seconds' => (int) env(
        'CLOUD_HEALTH_STATE_TTL_SECONDS',
        1_800,
    ),

    'lock_seconds' => (int) env(
        'CLOUD_HEALTH_LOCK_SECONDS',
        5,
    ),

    'lock_wait_seconds' => (int) env(
        'CLOUD_HEALTH_LOCK_WAIT_SECONDS',
        1,
    ),

    'thresholds' => [
        'degraded_after_failures' => (int) env(
            'CLOUD_HEALTH_DEGRADED_AFTER_FAILURES',
            1,
        ),

        'unavailable_after_failures' => (int) env(
            'CLOUD_HEALTH_UNAVAILABLE_AFTER_FAILURES',
            3,
        ),

        'recovery_successes' => (int) env(
            'CLOUD_HEALTH_RECOVERY_SUCCESSES',
            2,
        ),
    ],

    'probe' => [
        'enabled' => filter_var(
            env(
                'CLOUD_HEALTH_PROBE_ENABLED',
                true,
            ),
            FILTER_VALIDATE_BOOL,
        ),
    ],
];
