<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsPreflightException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsApplyResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Services\PlatformInstallationService;

final readonly class EnableMarzbanHttpsAction
{
    public function __construct(
        private InspectMarzbanHttpsAction $inspectAction,
        private PreflightMarzbanHttpsAction $preflightAction,
        private PlatformInstallationService $platforms,
        private MarzbanHttpsGateway $gateway,
    ) {}

    public function execute(
        string $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsApplyResult {
        $state = $this->inspectAction->execute()->state;

        if (! in_array(
            $state,
            [
                MarzbanHttpsState::Disabled,
                MarzbanHttpsState::ManagedIncomplete,
            ],
            true,
        )) {
            throw MarzbanHttpsApplyException::existingConfiguration();
        }

        $preflight = $this->preflightAction->execute(
            domain: $domain,
            knownServerAddress: $knownServerAddress,
        );

        if (! $preflight->ready()) {
            throw MarzbanHttpsPreflightException::notReady();
        }

        $this->platforms->ensure(
            PlatformType::Caddy,
        );

        return $this->gateway->enable(
            MarzbanDomain::from($preflight->dns->domain),
        );
    }
}
