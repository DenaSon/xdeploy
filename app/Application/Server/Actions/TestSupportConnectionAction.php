<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Models\Server;

final readonly class TestSupportConnectionAction
{
    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    public function handle(Server $server): void
    {
        try {
            if ($this->ssh->connect($server)) {
                return;
            }

            throw new SSHConnectionException(
                'Unable to establish SSH connection.',
            );
        } finally {
            $this->ssh->disconnect();
        }
    }
}
