<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Server\ServerManager;
use App\Domain\Server\DTOs\ServiceStatusData;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Models\Server;
use Illuminate\Console\Command;
use Throwable;

final class CheckServerServicesCommand extends Command
{
    protected $signature = 'server:services {server : Server ID}';

    protected $description = 'Show service statuses for a server';

    public function __construct(
        private readonly ServerManager $serverManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $server = Server::query()->find($this->argument('server'));

        if (! $server instanceof Server) {
            $this->error('Server not found.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Checking services for server #%d (%s)...',
            $server->getKey(),
            $server->name,
        ));

        try {
            $services = $this->serverManager->services($server);
        } catch (SSHConnectionException) {
            $this->error('Unable to connect to the server over SSH.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);

            $this->error('Unable to retrieve server services.');

            return self::FAILURE;
        }

        $this->table(
            ['Service', 'Status'],
            array_map(
                static fn (ServiceStatusData $service): array => [
                    $service->name,
                    $service->status,
                ],
                $services,
            ),
        );

        return self::SUCCESS;
    }
}
