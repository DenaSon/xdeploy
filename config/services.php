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

];
