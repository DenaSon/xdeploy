<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsApplyResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;

interface MarzbanHttpsGateway
{
    public function inspect(): MarzbanHttpsInfo;

    public function preflightDns(
        MarzbanDomain $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsDnsPreflightResult;

    public function preflightServer(): MarzbanHttpsServerPreflightResult;

    public function enable(
        MarzbanDomain $domain,
    ): MarzbanHttpsApplyResult;
}
