<?php

namespace App\Console\Commands;

use App\Domain\Server\Models\Server;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('server:test')]
#[Description('Test SSH connection to the first registered server')]
class TestSSHConnection extends Command
{
    public function __construct(
        private readonly SSHConnectionInterface $ssh,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $server = Server::query()
            ->where('is_active', true)
            ->first();

        if (! $server) {
            $this->error('No server found.');

            return self::FAILURE;
        }

        $this->info("Connecting to {$server->host}:{$server->port} ...");

        try {
            $connected = $this->ssh->connect(
                host: $server->host,
                port: $server->port,
                username: $server->username,
                authenticationType: $server->authentication_type,
                credential: $server->credential,
                privateKeyPath: $server->private_key_path,
            );

            if (! $connected) {
                $this->error('SSH connection failed.');

                return self::FAILURE;
            }

            $this->info('Connected successfully.');

            $hostname = trim(
                $this->ssh->execute('hostname')
            );

            $this->newLine();
            $this->info('Hostname:');
            $this->line($hostname);

            $this->ssh->disconnect();

            return self::SUCCESS;

        } catch (Throwable $e) {

            $this->ssh->disconnect();

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
