<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\Marzban;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPortInfo;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use PHPUnit\Framework\TestCase;

final class MarzbanHttpsPortInfoTest extends TestCase
{
    public function test_an_available_port_is_usable_by_xdeploy(): void
    {
        $port = new MarzbanHttpsPortInfo(
            port: 80,
            state: MarzbanHttpsPortState::Available,
            owner: MarzbanHttpsPortOwner::None,
        );

        self::assertTrue($port->availableForXDeploy());
        self::assertFalse($port->hasConflict());
    }

    public function test_an_existing_managed_port_is_reusable(): void
    {
        $port = new MarzbanHttpsPortInfo(
            port: 443,
            state: MarzbanHttpsPortState::Managed,
            owner: MarzbanHttpsPortOwner::XDeployCaddy,
        );

        self::assertTrue($port->availableForXDeploy());
        self::assertFalse($port->hasConflict());
    }

    public function test_an_external_owner_is_a_conflict(): void
    {
        $port = new MarzbanHttpsPortInfo(
            port: 443,
            state: MarzbanHttpsPortState::Conflict,
            owner: MarzbanHttpsPortOwner::Nginx,
        );

        self::assertFalse($port->availableForXDeploy());
        self::assertTrue($port->hasConflict());
    }
}
