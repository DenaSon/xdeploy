<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'fake'),
    ],

    'smsir' => [
        'template_id' => (int) env('SMSIR_TEMPLATE_ID'),
        'parameter_name' => env('SMSIR_PARAMETER_NAME', 'Code'),
    ],


    'arvan_cloud' => [
        'api_key' => env('ARVAN_CLOUD_API_KEY'),
        'region' => env('ARVAN_CLOUD_REGION', 'ir-thr-c2'),
        'base_url' => env(
            'ARVAN_CLOUD_BASE_URL',
            'https://napi.arvancloud.ir/ecc/v1'
        ),
    ],



];
