<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban;

use App\Application\Applications\Marzban\Actions\CreateMarzbanAdminAction;
use App\Application\Applications\Marzban\Actions\EnableMarzbanHttpsAction;
use App\Application\Applications\Marzban\Actions\GetMarzbanManagementOverviewAction;
use App\Application\Applications\Marzban\Actions\PreflightMarzbanHttpsAction;
use App\Application\Applications\Marzban\Actions\PreflightMarzbanHttpsDomainAction;
use App\Application\Applications\Marzban\DTOs\CreateMarzbanAdminData;
use App\Application\Applications\Marzban\DTOs\MarzbanManagementData;
use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPreflightResult;
use App\Models\Server;

final readonly class MarzbanManager
{
    public function __construct(
        private ConnectServerAction $connectServerAction,
        private GetMarzbanManagementOverviewAction $getOverviewAction,
        private CreateMarzbanAdminAction $createAdminAction,
        private PreflightMarzbanHttpsDomainAction $preflightHttpsDomainAction,
        private PreflightMarzbanHttpsAction $preflightHttpsAction,
        private EnableMarzbanHttpsAction $enableHttpsAction,
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

    public function preflightHttpsDomain(
        Server $server,
        string $domain,
    ): MarzbanHttpsDnsPreflightResult {
        $this->connect($server);

        return $this->preflightHttpsDomainAction->execute(
            domain: $domain,
            knownServerAddress: $server->host,
        );
    }

    public function preflightHttps(
        Server $server,
        string $domain,
    ): MarzbanHttpsPreflightResult {
        $this->connect($server);

        return $this->preflightHttpsAction->execute(
            domain: $domain,
            knownServerAddress: $server->host,
        );
    }

    public function enableHttps(
        Server $server,
        string $domain,
    ): MarzbanManagementData {
        $this->connect($server);

        $this->enableHttpsAction->execute(
            domain: $domain,
            knownServerAddress: $server->host,
        );

        return $this->getOverviewAction->execute();
    }

    private function connect(Server $server): void
    {
        $this->connectServerAction->handle($server);
    }
}
