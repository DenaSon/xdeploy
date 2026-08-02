<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsInspectionException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;

final readonly class InspectMarzbanHttpsAction
{
    public function __construct(
        private MarzbanHttpsGateway $gateway,
    ) {}

    public function execute(): MarzbanHttpsInfo
    {
        try {
            return $this->gateway->inspect();
        } catch (MarzbanHttpsInspectionException) {
            return MarzbanHttpsInfo::unknown();
        }
    }
}
