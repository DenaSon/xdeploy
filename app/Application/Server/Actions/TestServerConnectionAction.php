<?php

namespace App\Application\Server\Actions;

use App\Application\Server\Data\TestServerConnectionData;
use App\Domain\Server\Enums\AuthenticationType;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Models\Server;

final readonly class TestServerConnectionAction
{
    public function __construct(
        private SSHConnectionInterface $connection,
    ) {}

    public function execute(TestServerConnectionData $data): bool
    {
        $server = new Server([
            'host' => $data->host,
            'port' => $data->port,
            'username' => $data->username,
            'credential' => $data->credential,
            'authentication_type' => AuthenticationType::Password,
        ]);

        try {
            return $this->connection->connect($server);
        } finally {
            $this->connection->disconnect();
        }
    }
}
