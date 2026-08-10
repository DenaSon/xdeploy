<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Admin\DTOs\MarzbanAdminOverview;
use App\Domain\Application\Marzban\Admin\MarzbanAdminReader;
use App\Domain\Application\Marzban\Exceptions\MarzbanSetupInspectionException;

final readonly class InspectMarzbanAdminsAction
{
    public function __construct(
        private MarzbanAdminReader $adminReader,
    ) {}

    public function execute(): MarzbanAdminOverview
    {
        try {
            return $this->adminReader->overview();
        } catch (MarzbanSetupInspectionException) {
            return MarzbanAdminOverview::unknown();
        }
    }
}
