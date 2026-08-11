<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | ArvanCloud prices are returned in Iranian Rials.
    |
    */

    'currency' => 'IRR',

    'quote_ttl_minutes' => 15,

    /*
    |--------------------------------------------------------------------------
    | xDeploy Markup
    |--------------------------------------------------------------------------
    */

    'markup_percent' => 75,

    /*
    |--------------------------------------------------------------------------
    | Renewal payment protection
    |--------------------------------------------------------------------------
    |
    | When a Cloud VPS expires while a renewal payment is already initiating
    | or pending, termination is delayed briefly so the payment callback can
    | finish safely. Abandoned attempts stop protecting the resource after
    | this window.
    |
    */

    'renewal_payment_protection_minutes' => 30,

    /*
    |--------------------------------------------------------------------------
    | Purchase Periods
    |--------------------------------------------------------------------------
    */

    'periods' => [

        '2_days' => [
            'label' => '۲ روزه',
            'hours' => 48,
            'pricing' => 'hourly',
        ],

        '14_days' => [
            'label' => '۱۴ روزه',
            'hours' => 336,
            'pricing' => 'hourly',
        ],

        '1_month' => [
            'label' => '۱ ماهه',
            'hours' => 720,
            'pricing' => 'monthly',
        ],

    ],

];
