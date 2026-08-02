<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\Marzban;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPortInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsLayoutState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use PHPUnit\Framework\TestCase;

final class MarzbanHttpsPreflightResultTest extends TestCase
{
    public function test_it_requires_both_dns_and_server_readiness(): void
    {
        $result = new MarzbanHttpsPreflightResult(
            dns: new MarzbanHttpsDnsPreflightResult(
                domain: 'panel.example.com',
                serverIpv4Address: '203.0.113.10',
                resolvedIpv4Addresses: ['203.0.113.10'],
                resolvedIpv6Addresses: [],
            ),
            server: new MarzbanHttpsServerPreflightResult(
                layoutState: MarzbanHttpsLayoutState::Supported,
                managedCaddyDetected: false,
                port80: $this->availablePort(80),
                port443: $this->availablePort(443),
            ),
        );

        self::assertTrue($result->ready());
        self::assertTrue($result->toArray()['ready']);
    }

    public function test_server_preflight_is_skipped_when_dns_is_not_ready(): void
    {
        $result = new MarzbanHttpsPreflightResult(
            dns: new MarzbanHttpsDnsPreflightResult(
                domain: 'panel.example.com',
                serverIpv4Address: '203.0.113.10',
                resolvedIpv4Addresses: ['203.0.113.11'],
                resolvedIpv6Addresses: [],
            ),
            server: null,
        );

        self::assertFalse($result->ready());
        self::assertNull($result->toArray()['server']);
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
