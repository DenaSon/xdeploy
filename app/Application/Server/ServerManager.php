<?php

namespace App\Application\Server;

use App\Application\Server\Actions\ConnectServerAction;
use App\Application\Server\Actions\GetServerOverviewAction;
use App\Domain\Server\DTOs\ServerOverviewData;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;

readonly class ServerManager
{
    public function __construct(
        private ConnectServerAction $connectServer,
        private GetServerOverviewAction $getServerOverview,
    ) {}

    public function overview(Server $server): ServerOverviewData
    {
        return Cache::remember(
            "server:{$server->id}:overview",
            now()->addMinutes(5),
            function () use ($server) {

                $this->connectServer->handle($server);

                return $this->getServerOverview->handle();
            }
        );
    }
}
