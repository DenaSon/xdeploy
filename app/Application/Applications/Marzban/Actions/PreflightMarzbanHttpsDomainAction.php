<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;

final readonly class PreflightMarzbanHttpsDomainAction
{
    public function __construct(
        private MarzbanHttpsGateway $gateway,
    ) {}

    public function execute(
        string $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsDnsPreflightResult {
        return $this->gateway->preflightDns(
            domain: MarzbanDomain::from($domain),
            knownServerAddress: $knownServerAddress,
        );
    }
}
