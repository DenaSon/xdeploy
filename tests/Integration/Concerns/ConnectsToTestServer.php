<?php

declare(strict_types=1);

namespace Tests\Integration\Concerns;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

trait ConnectsToTestServer
{
    protected SSHConnectionInterface $ssh;

    protected function connectToTestServer(): void
    {
        $this->ssh = app(SSHConnectionInterface::class);

        $connected = $this->ssh->connect(
            host: env('TEST_SSH_HOST'),
            port: (int) env('TEST_SSH_PORT', 22),
            username: env('TEST_SSH_USERNAME'),
            authenticationType: env('TEST_SSH_AUTH', 'password'),
            credential: env('TEST_SSH_PASSWORD'),
            privateKeyPath: env('TEST_SSH_PRIVATE_KEY'),
        );

        expect($connected)->toBeTrue();
    }
}
