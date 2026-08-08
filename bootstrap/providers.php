<?php

use App\Providers\ApplicationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CloudServiceProvider;
use App\Providers\CloudSnapshotServiceProvider;
use App\Providers\CredentialSecurityServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\PlatformServiceProvider;
use App\Providers\ServerServiceProvider;
use App\Providers\SmsServiceProvider;
use App\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,
    ServerServiceProvider::class,
    ApplicationServiceProvider::class,
    PlatformServiceProvider::class,
    CloudServiceProvider::class,
    SmsServiceProvider::class,
    UserServiceProvider::class,
    CredentialSecurityServiceProvider::class,
    CloudSnapshotServiceProvider::class,
    PaymentServiceProvider::class,
];
