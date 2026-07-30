<?php

declare(strict_types=1);

namespace App\Application\Applications\Manager;

use App\Application\Applications\Actions\GetModulesOverviewAction;
use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Application\DTOs\ModuleInfo;
use App\Domain\Application\Enums\ModuleType;
use App\Models\Server;

final readonly class ModulesManager
{
    public function __construct(
        private ConnectServerAction $connectServer,
        private GetModulesOverviewAction $getModulesOverview,
    ) {}

    /**
     * @return array<int, array{
     *     type: ModuleType,
     *     name: string,
     *     info: ModuleInfo,
     * }>
     */
    public function overview(Server $server): array
    {
        $this->connectServer->handle($server);

        return $this->getModulesOverview->handle();
    }
}
