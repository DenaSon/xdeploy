<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Admin\MarzbanAdminGateway;
use App\Domain\Application\Marzban\Exceptions\MarzbanSetupInspectionException;
use App\Domain\Application\Marzban\Setup\Enums\MarzbanSetupState;

final readonly class InspectMarzbanSetupAction
{
    public function __construct(
        private MarzbanAdminGateway $adminGateway,
    ) {}

    public function execute(): MarzbanSetupState
    {
        try {
            return $this->adminGateway->inspect();
        } catch (MarzbanSetupInspectionException) {
            return MarzbanSetupState::Unknown;
        }
    }
}
