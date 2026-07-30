<?php

declare(strict_types=1);

namespace App\Application\Applications\Manager;

use App\Application\Applications\Actions\GetApplicationOverviewAction;
use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\Server;

final readonly class ApplicationManager
{
    public function __construct(
        private ConnectServerAction          $connectServer,
        private GetApplicationOverviewAction $getModulesOverview,
    ) {}

    /**
     * @return array<int, array{
     *     type: ApplicationType,
     *     name: string,
     *     info: ApplicationInfo,
     * }>
     */
    public function overview(Server $server): array
    {
        $this->connectServer->handle($server);

        return $this->getModulesOverview->handle();
    }
}
