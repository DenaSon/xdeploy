<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\Marzban;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPortInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsLayoutState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use PHPUnit\Framework\TestCase;

final class MarzbanHttpsServerPreflightResultTest extends TestCase
{
    public function test_it_is_ready_for_supported_layout_and_free_ports(): void
    {
        $result = $this->makeResult(
            layout: MarzbanHttpsLayoutState::Supported,
            port80State: MarzbanHttpsPortState::Available,
            port443State: MarzbanHttpsPortState::Available,
        );

        self::assertTrue($result->layoutSupported());
        self::assertFalse($result->hasPortConflict());
        self::assertTrue($result->ready());
    }

    public function test_a_port_conflict_blocks_the_preflight(): void
    {
        $result = $this->makeResult(
            layout: MarzbanHttpsLayoutState::Supported,
            port80State: MarzbanHttpsPortState::Conflict,
            port443State: MarzbanHttpsPortState::Available,
        );

        self::assertTrue($result->hasPortConflict());
        self::assertFalse($result->ready());
    }

    public function test_an_unsupported_layout_blocks_the_preflight(): void
    {
        $result = $this->makeResult(
            layout: MarzbanHttpsLayoutState::UnsupportedCompose,
            port80State: MarzbanHttpsPortState::Available,
            port443State: MarzbanHttpsPortState::Available,
        );

        self::assertFalse($result->layoutSupported());
        self::assertFalse($result->ready());
    }

    private function makeResult(
        MarzbanHttpsLayoutState $layout,
        MarzbanHttpsPortState $port80State,
        MarzbanHttpsPortState $port443State,
    ): MarzbanHttpsServerPreflightResult {
        return new MarzbanHttpsServerPreflightResult(
            layoutState: $layout,
            managedCaddyDetected: false,
            port80: new MarzbanHttpsPortInfo(
                port: 80,
                state: $port80State,
                owner: $port80State === MarzbanHttpsPortState::Conflict
                    ? MarzbanHttpsPortOwner::Nginx
                    : MarzbanHttpsPortOwner::None,
            ),
            port443: new MarzbanHttpsPortInfo(
                port: 443,
                state: $port443State,
                owner: $port443State === MarzbanHttpsPortState::Conflict
                    ? MarzbanHttpsPortOwner::Nginx
                    : MarzbanHttpsPortOwner::None,
            ),
        );
    }
}
