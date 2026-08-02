<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPreflightResult;

final readonly class PreflightMarzbanHttpsAction
{
    public function __construct(
        private PreflightMarzbanHttpsDomainAction $domainAction,
        private PreflightMarzbanHttpsServerAction $serverAction,
    ) {}

    public function execute(
        string $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsPreflightResult {
        $dns = $this->domainAction->execute(
            domain: $domain,
            knownServerAddress: $knownServerAddress,
        );

        return new MarzbanHttpsPreflightResult(
            dns: $dns,
            server: $dns->ready()
                ? $this->serverAction->execute()
                : null,
        );
    }
}
