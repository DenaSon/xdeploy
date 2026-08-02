<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Applications\Marzban;

use App\Application\Applications\Marzban\Actions\EnableMarzbanHttpsAction;
use App\Application\Applications\Marzban\Actions\InspectMarzbanHttpsAction;
use App\Application\Applications\Marzban\Actions\PreflightMarzbanHttpsAction;
use App\Application\Applications\Marzban\Actions\PreflightMarzbanHttpsDomainAction;
use App\Application\Applications\Marzban\Actions\PreflightMarzbanHttpsServerAction;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsPreflightException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsApplyResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPortInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsLayoutState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use PHPUnit\Framework\TestCase;

final class EnableMarzbanHttpsActionTest extends TestCase
{
    public function test_it_applies_https_only_after_a_fresh_ready_preflight(): void
    {
        $gateway = new EnableActionFakeGateway;

        $result = $this->makeAction($gateway)->execute(
            domain: 'PANEL.EXAMPLE.COM.',
            knownServerAddress: '203.0.113.10',
        );

        self::assertTrue($gateway->enableCalled);
        self::assertSame('panel.example.com', $gateway->enabledDomain);
        self::assertSame('panel.example.com', $result->domain);
    }

    public function test_it_does_not_apply_when_the_fresh_preflight_is_not_ready(): void
    {
        $gateway = new EnableActionFakeGateway;
        $gateway->dnsReady = false;

        $this->expectException(
            MarzbanHttpsPreflightException::class,
        );

        try {
            $this->makeAction($gateway)->execute(
                domain: 'panel.example.com',
                knownServerAddress: '203.0.113.10',
            );
        } finally {
            self::assertFalse($gateway->enableCalled);
        }
    }

    public function test_it_never_overwrites_an_existing_external_configuration(): void
    {
        $gateway = new EnableActionFakeGateway;
        $gateway->httpsInfo = new MarzbanHttpsInfo(
            state: MarzbanHttpsState::ManagedExternally,
            domain: 'panel.example.com',
        );

        try {
            $this->makeAction($gateway)->execute(
                domain: 'panel.example.com',
                knownServerAddress: '203.0.113.10',
            );

            self::fail('Expected an apply exception.');
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::ExistingConfiguration,
                $exception->failure,
            );
            self::assertFalse($gateway->enableCalled);
        }
    }

    private function makeAction(
        EnableActionFakeGateway $gateway,
    ): EnableMarzbanHttpsAction {
        return new EnableMarzbanHttpsAction(
            inspectAction: new InspectMarzbanHttpsAction($gateway),
            preflightAction: new PreflightMarzbanHttpsAction(
                domainAction: new PreflightMarzbanHttpsDomainAction(
                    $gateway,
                ),
                serverAction: new PreflightMarzbanHttpsServerAction(
                    $gateway,
                ),
            ),
            gateway: $gateway,
        );
    }
}

final class EnableActionFakeGateway implements MarzbanHttpsGateway
{
    public MarzbanHttpsInfo $httpsInfo;

    public bool $dnsReady = true;

    public bool $enableCalled = false;

    public ?string $enabledDomain = null;

    public function __construct()
    {
        $this->httpsInfo = new MarzbanHttpsInfo(
            state: MarzbanHttpsState::Disabled,
        );
    }

    public function inspect(): MarzbanHttpsInfo
    {
        return $this->httpsInfo;
    }

    public function preflightDns(
        MarzbanDomain $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsDnsPreflightResult {
        return new MarzbanHttpsDnsPreflightResult(
            domain: $domain->value,
            serverIpv4Address: '203.0.113.10',
            resolvedIpv4Addresses: [
                $this->dnsReady
                    ? '203.0.113.10'
                    : '203.0.113.11',
            ],
            resolvedIpv6Addresses: [],
        );
    }

    public function preflightServer(): MarzbanHttpsServerPreflightResult
    {
        return new MarzbanHttpsServerPreflightResult(
            layoutState: MarzbanHttpsLayoutState::Supported,
            managedCaddyDetected: false,
            port80: $this->availablePort(80),
            port443: $this->availablePort(443),
        );
    }

    public function enable(
        MarzbanDomain $domain,
    ): MarzbanHttpsApplyResult {
        $this->enableCalled = true;
        $this->enabledDomain = $domain->value;

        return new MarzbanHttpsApplyResult(
            domain: $domain->value,
            panelUrl: "https://{$domain->value}/dashboard/",
            configurationChanged: true,
        );
    }

    private function availablePort(int $port): MarzbanHttpsPortInfo
    {
        return new MarzbanHttpsPortInfo(
            port: $port,
            state: MarzbanHttpsPortState::Available,
            owner: MarzbanHttpsPortOwner::None,
        );
    }
}
