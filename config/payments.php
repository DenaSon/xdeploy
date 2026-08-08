<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */

    'default' => env(
        'PAYMENT_GATEWAY',
        'zarinpal',
    ),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    */

    'gateways' => [

        'zarinpal' => [
            'merchant_id' => env(
                'ZARINPAL_MERCHANT_ID',
            ),

            /*
             * Kept available for ZarinPal SDK capabilities that use the
             * account API access token. Payment request/verify itself uses
             * the Merchant ID.
             */
            'access_token' => env(
                'ZARINPAL_ACCESS_TOKEN',
                '',
            ),

            'sandbox' => env(
                'ZARINPAL_SANDBOX',
                false,
            ),
        ],

    ],

];
