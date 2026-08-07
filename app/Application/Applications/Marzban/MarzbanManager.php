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
use App\Models\User;

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
        User $user,
        Server $server,
    ): MarzbanManagementData {
        $this->connect(
            user: $user,
            server: $server,
        );

        return $this->getOverviewAction->execute();
    }

    public function createAdmin(
        User $user,
        Server $server,
        CreateMarzbanAdminData $data,
    ): MarzbanManagementData {
        $this->connect(
            user: $user,
            server: $server,
        );

        $this->createAdminAction->execute(
            $data,
        );

        return $this->getOverviewAction->execute();
    }

    public function preflightHttpsDomain(
        User $user,
        Server $server,
        string $domain,
    ): MarzbanHttpsDnsPreflightResult {
        $ownedServer = $this->connect(
            user: $user,
            server: $server,
        );

        return $this->preflightHttpsDomainAction->execute(
            domain: $domain,
            knownServerAddress: $ownedServer->host,
        );
    }

    public function preflightHttps(
        User $user,
        Server $server,
        string $domain,
    ): MarzbanHttpsPreflightResult {
        $ownedServer = $this->connect(
            user: $user,
            server: $server,
        );

        return $this->preflightHttpsAction->execute(
            domain: $domain,
            knownServerAddress: $ownedServer->host,
        );
    }

    public function enableHttps(
        User $user,
        Server $server,
        string $domain,
    ): MarzbanManagementData {
        $ownedServer = $this->connect(
            user: $user,
            server: $server,
        );

        $this->enableHttpsAction->execute(
            domain: $domain,
            knownServerAddress: $ownedServer->host,
        );

        return $this->getOverviewAction->execute();
    }

    private function connect(
        User $user,
        Server $server,
    ): Server {
        $ownedServer = Server::query()
            ->ownedBy($user)
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        $this->connectServerAction->handle(
            $ownedServer,
        );

        return $ownedServer;
    }
}
