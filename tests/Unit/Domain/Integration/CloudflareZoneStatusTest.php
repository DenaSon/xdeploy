<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Integration;

use App\Domain\Integration\Cloudflare\CloudflareZoneStatus;
use PHPUnit\Framework\TestCase;

final class CloudflareZoneStatusTest extends TestCase
{
    public function test_it_normalizes_known_and_unknown_remote_statuses(): void
    {
        self::assertSame(
            CloudflareZoneStatus::Pending,
            CloudflareZoneStatus::fromRemote(' PENDING '),
        );
        self::assertSame(
            CloudflareZoneStatus::Active,
            CloudflareZoneStatus::fromRemote('active'),
        );
        self::assertSame(
            CloudflareZoneStatus::Unknown,
            CloudflareZoneStatus::fromRemote('future-status'),
        );
        self::assertSame(
            CloudflareZoneStatus::Unknown,
            CloudflareZoneStatus::fromRemote(null),
        );
    }
}
