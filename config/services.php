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
        'expiring_soon_template_id' => (int) env('SMSIR_EXPIRING_SOON_TEMPLATE_ID'),
        'expiring_soon_parameter_name' => env('SMSIR_EXPIRING_SOON_PARAMETER_NAME', 'Hours'),
    ],

];
