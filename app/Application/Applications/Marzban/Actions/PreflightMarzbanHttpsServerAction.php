<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;

final readonly class PreflightMarzbanHttpsServerAction
{
    public function __construct(
        private MarzbanHttpsGateway $gateway,
    ) {}

    public function execute(): MarzbanHttpsServerPreflightResult
    {
        return $this->gateway->preflightServer();
    }
}
