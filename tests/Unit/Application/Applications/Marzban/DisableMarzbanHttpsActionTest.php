<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Applications\Marzban;

use App\Application\Applications\Marzban\Actions\DisableMarzbanHttpsAction;
use App\Application\Applications\Marzban\Actions\InspectMarzbanHttpsAction;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsApplyResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Marzban\Https\MarzbanHttpsDisabler;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use LogicException;
use PHPUnit\Framework\TestCase;

final class DisableMarzbanHttpsActionTest extends TestCase
{
    public function test_it_disables_an_enabled_managed_domain(): void
    {
        $gateway = new DisableActionFakeGateway(
            new MarzbanHttpsInfo(
                state: MarzbanHttpsState::Enabled,
                domain: 'panel.example.com',
            ),
        );
        $disabler = new DisableActionFakeDisabler;

        $this->action($gateway, $disabler)->execute(
            'PANEL.EXAMPLE.COM.',
        );

        self::assertSame(
            'panel.example.com',
            $disabler->disabledDomain,
        );
    }

    public function test_it_can_remove_a_managed_incomplete_endpoint_using_the_persisted_domain(): void
    {
        $gateway = new DisableActionFakeGateway(
            new MarzbanHttpsInfo(
                state: MarzbanHttpsState::ManagedIncomplete,
            ),
        );
        $disabler = new DisableActionFakeDisabler;

        $this->action($gateway, $disabler)->execute(
            'panel.example.com',
        );

        self::assertSame(
            'panel.example.com',
            $disabler->disabledDomain,
        );
    }

    public function test_it_treats_an_already_disabled_runtime_as_idempotent(): void
    {
        $gateway = new DisableActionFakeGateway(
            new MarzbanHttpsInfo(
                state: MarzbanHttpsState::Disabled,
            ),
        );
        $disabler = new DisableActionFakeDisabler;

        $this->action($gateway, $disabler)->execute(
            'panel.example.com',
        );

        self::assertNull($disabler->disabledDomain);
    }

    public function test_it_never_removes_an_external_configuration(): void
    {
        $gateway = new DisableActionFakeGateway(
            new MarzbanHttpsInfo(
                state: MarzbanHttpsState::ManagedExternally,
                domain: 'panel.example.com',
            ),
        );
        $disabler = new DisableActionFakeDisabler;

        try {
            $this->action($gateway, $disabler)->execute(
                'panel.example.com',
            );

            self::fail('Expected an apply exception.');
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::ExistingConfiguration,
                $exception->failure,
            );
            self::assertNull($disabler->disabledDomain);
        }
    }

    public function test_it_rejects_a_domain_that_does_not_match_the_enabled_runtime(): void
    {
        $gateway = new DisableActionFakeGateway(
            new MarzbanHttpsInfo(
                state: MarzbanHttpsState::Enabled,
                domain: 'current.example.com',
            ),
        );
        $disabler = new DisableActionFakeDisabler;

        try {
            $this->action($gateway, $disabler)->execute(
                'other.example.com',
            );

            self::fail('Expected an apply exception.');
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::ExistingConfiguration,
                $exception->failure,
            );
            self::assertNull($disabler->disabledDomain);
        }
    }

    private function action(
        DisableActionFakeGateway $gateway,
        DisableActionFakeDisabler $disabler,
    ): DisableMarzbanHttpsAction {
        return new DisableMarzbanHttpsAction(
            inspectAction: new InspectMarzbanHttpsAction($gateway),
            disabler: $disabler,
        );
    }
}

final class DisableActionFakeDisabler implements MarzbanHttpsDisabler
{
    public ?string $disabledDomain = null;

    public function disable(MarzbanDomain $domain): void
    {
        $this->disabledDomain = $domain->value;
    }
}

final readonly class DisableActionFakeGateway implements MarzbanHttpsGateway
{
    public function __construct(
        private MarzbanHttpsInfo $info,
    ) {}

    public function inspect(): MarzbanHttpsInfo
    {
        return $this->info;
    }

    public function preflightDns(
        MarzbanDomain $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsDnsPreflightResult {
        throw new LogicException('Not used by this test.');
    }

    public function preflightServer(): MarzbanHttpsServerPreflightResult
    {
        throw new LogicException('Not used by this test.');
    }

    public function enable(
        MarzbanDomain $domain,
    ): MarzbanHttpsApplyResult {
        throw new LogicException('Not used by this test.');
    }
}
