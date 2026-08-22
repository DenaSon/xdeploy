<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Infrastructure\Cloud\Observability\CloudProviderHttpObserver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class CloudObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CloudProviderHttpObserver::class,
            static fn (Application $app): CloudProviderHttpObserver => new CloudProviderHttpObserver(
                $app->make(CloudProviderHealthEngine::class),
            ),
        );
    }

    public function boot(
        CloudProviderHttpObserver $observer,
    ): void {
        Event::listen(
            RequestSending::class,
            static function (RequestSending $event) use ($observer): void {
                $observer->requestSending($event);
            },
        );

        Event::listen(
            ResponseReceived::class,
            static function (ResponseReceived $event) use ($observer): void {
                $observer->responseReceived($event);
            },
        );

        Event::listen(
            ConnectionFailed::class,
            static function (ConnectionFailed $event) use ($observer): void {
                $observer->connectionFailed($event);
            },
        );
    }
}
