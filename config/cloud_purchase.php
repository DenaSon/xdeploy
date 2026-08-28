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
         * ParsPack charges a non-refundable seven-day initial setup period,
         * so shorter Coreflare plans must never be exposed for this provider.
         */
        'parspack' => [
            '7_days',
            '1_month',
        ],
    ],
];
