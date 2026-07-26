<?php

namespace App\Console\Commands;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\ServerInformation;
use App\Models\Server;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('server:test')]
#[Description('Test SSH connection to the active server')]
class TestSSHConnectionCommand extends Command
{
    public function __construct(
        private readonly SSHConnectionInterface $ssh,
        private readonly ServerInformation $serverInformation,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $server = Server::query()
            ->where('is_active', true)
            ->first();

        if (! $server) {
            $this->error('No active server found.');

            return self::FAILURE;
        }

        $this->info("Connecting to {$server->host}:{$server->port}...");

        try {

            $this->ssh->connect(
                host: $server->host,
                port: $server->port,
                username: $server->username,
                authenticationType: $server->authentication_type,
                credential: $server->credential,
                privateKeyPath: $server->private_key_path,
            );

            $this->info('Connected successfully.');

            $this->newLine();

            $this->info('Server Information');

            $this->table(
                ['Property', 'Value'],
                [
                    ['Hostname', $this->serverInformation->hostname()],
                    ['User', $this->serverInformation->whoami()],
                    ['Operating System', $this->serverInformation->os()],
                    ['Uptime', $this->serverInformation->uptime()],
                ]
            );

            return self::SUCCESS;

        } catch (Throwable $e) {

            $this->newLine();

            $this->error('SSH Connection Error');

            $this->line($e->getMessage());

            return self::FAILURE;

        } finally {

            $this->ssh->disconnect();

        }
    }
}
