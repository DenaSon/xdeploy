<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\Marzban;

use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use PHPUnit\Framework\TestCase;

final class MarzbanHttpsDnsPreflightResultTest extends TestCase
{
    public function test_it_is_ready_for_one_matching_a_record(): void
    {
        $result = new MarzbanHttpsDnsPreflightResult(
            domain: 'panel.example.com',
            serverIpv4Address: '203.0.113.10',
            resolvedIpv4Addresses: ['203.0.113.10'],
            resolvedIpv6Addresses: [],
        );

        self::assertTrue($result->ipv4MatchesServer());
        self::assertFalse($result->hasIncompatibleIpv6());
        self::assertTrue($result->ready());
    }

    public function test_it_rejects_an_additional_a_record(): void
    {
        $result = new MarzbanHttpsDnsPreflightResult(
            domain: 'panel.example.com',
            serverIpv4Address: '203.0.113.10',
            resolvedIpv4Addresses: [
                '203.0.113.10',
                '203.0.113.11',
            ],
            resolvedIpv6Addresses: [],
        );

        self::assertFalse($result->ipv4MatchesServer());
        self::assertFalse($result->ready());
    }

    public function test_it_rejects_an_incompatible_aaaa_record(): void
    {
        $result = new MarzbanHttpsDnsPreflightResult(
            domain: 'panel.example.com',
            serverIpv4Address: '203.0.113.10',
            resolvedIpv4Addresses: ['203.0.113.10'],
            resolvedIpv6Addresses: ['2001:db8::10'],
        );

        self::assertTrue($result->ipv4MatchesServer());
        self::assertTrue($result->hasIncompatibleIpv6());
        self::assertFalse($result->ready());
    }
}
