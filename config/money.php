<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Cloud provider prices are normalized to Iranian Rials at their
    | infrastructure boundary before entering Coreflare billing logic.
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
    | Purchase Period Catalog
    |--------------------------------------------------------------------------
    |
    | This is the canonical period definition catalog. Provider-specific
    | availability is controlled separately by cloud_purchase.php.
    |
    */

    'periods' => [

        '2_days' => [
            'label' => '۲ روزه',
            'hours' => 48,
            'pricing' => 'hourly',
        ],

        '7_days' => [
            'label' => '۷ روزه',
            'hours' => 168,
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
