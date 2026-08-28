<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Provider Purchase Periods
    |--------------------------------------------------------------------------
    |
    | Period definitions live in money.periods. This matrix only controls
    | which of those canonical periods each provider may commercially expose.
    |
    */
    'periods' => [
        'arvan' => [
            '2_days',
            '14_days',
            '1_month',
        ],

        'liara' => [
            '2_days',
            '14_days',
            '1_month',
        ],

        /*
         * ParsPack purchases intentionally start at Coreflare's 14-day plan.
         * Shorter catalog periods stay unavailable for this provider.
         */
        'parspack' => [
            '14_days',
            '1_month',
        ],
    ],
];
