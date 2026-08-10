<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Application\Applications\Marzban\DTOs\MarzbanManagementData;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Marzban\Admin\DTOs\MarzbanAdminOverview;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Shared\Enums\ApplicationType;

final readonly class GetMarzbanManagementOverviewAction
{
    public function __construct(
        private ApplicationRegistryInterface $applicationRegistry,
        private InspectMarzbanAdminsAction $inspectAdminsAction,
        private InspectMarzbanHttpsAction $inspectHttpsAction,
    ) {}

    public function execute(): MarzbanManagementData
    {
        $application = $this->applicationRegistry
            ->find(ApplicationType::Marzban)
            ->inspect();

        if ($application->isNotInstalled()) {
            return new MarzbanManagementData(
                application: $application,
                setup: MarzbanAdminOverview::notRequired(),
                https: new MarzbanHttpsInfo(
                    state: MarzbanHttpsState::Disabled,
                ),
            );
        }

        if ($application->isUnknown()) {
            return new MarzbanManagementData(
                application: $application,
                setup: MarzbanAdminOverview::unknown(),
                https: MarzbanHttpsInfo::unknown(),
            );
        }

        return new MarzbanManagementData(
            application: $application,
            setup: $this->inspectAdminsAction->execute(),
            https: $this->inspectHttpsAction->execute(),
        );
    }
}
