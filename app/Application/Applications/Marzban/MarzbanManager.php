<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban;

use App\Application\Applications\Marzban\Actions\CreateMarzbanAdminAction;
use App\Application\Applications\Marzban\Actions\GetMarzbanManagementOverviewAction;
use App\Application\Applications\Marzban\DTOs\CreateMarzbanAdminData;
use App\Application\Applications\Marzban\DTOs\MarzbanManagementData;
use App\Application\Server\Actions\ConnectServerAction;
use App\Models\Server;

final readonly class MarzbanManager
{
    public function __construct(
        private ConnectServerAction $connectServerAction,
        private GetMarzbanManagementOverviewAction $getOverviewAction,
        private CreateMarzbanAdminAction $createAdminAction,
    ) {}

    public function overview(
        Server $server,
    ): MarzbanManagementData {
        $this->connect($server);

        return $this->getOverviewAction->execute();
    }

    public function createAdmin(
        Server $server,
        CreateMarzbanAdminData $data,
    ): MarzbanManagementData {
        $this->connect($server);

        $this->createAdminAction->execute($data);

        return $this->getOverviewAction->execute();
    }

    private function connect(Server $server): void
    {
        $this->connectServerAction->handle($server);
    }
}
