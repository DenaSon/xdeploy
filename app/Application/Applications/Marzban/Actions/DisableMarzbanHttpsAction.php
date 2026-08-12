<?php

declare(strict_types=1);

namespace App\Application\Applications\Marzban\Actions;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Marzban\Https\MarzbanHttpsDisabler;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;

final readonly class DisableMarzbanHttpsAction
{
    public function __construct(
        private InspectMarzbanHttpsAction $inspectAction,
        private MarzbanHttpsDisabler $disabler,
    ) {}

    public function execute(string $domain): void
    {
        $domain = MarzbanDomain::from($domain);
        $current = $this->inspectAction->execute();

        if ($current->state === MarzbanHttpsState::Disabled) {
            return;
        }

        if (! in_array(
            $current->state,
            [
                MarzbanHttpsState::Enabled,
                MarzbanHttpsState::ManagedIncomplete,
            ],
            true,
        )) {
            throw MarzbanHttpsApplyException::existingConfiguration();
        }

        if (
            $current->state === MarzbanHttpsState::Enabled
            && $current->domain !== $domain->value
        ) {
            throw MarzbanHttpsApplyException::existingConfiguration();
        }

        $this->disabler->disable($domain);
    }
}
