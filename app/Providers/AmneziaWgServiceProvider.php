<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Application\AmneziaWg\Peer\AmneziaWgPeerGateway;
use App\Infrastructure\Application\AmneziaWg\SshAmneziaWgPeerGateway;
use Illuminate\Support\ServiceProvider;

final class AmneziaWgServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AmneziaWgPeerGateway::class,
            SshAmneziaWgPeerGateway::class,
        );
    }
}
