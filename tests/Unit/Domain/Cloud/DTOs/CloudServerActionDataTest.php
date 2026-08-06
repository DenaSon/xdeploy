<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cloud\DTOs;

use App\Domain\Cloud\DTOs\CloudServerActionData;
use PHPUnit\Framework\TestCase;

final class CloudServerActionDataTest extends TestCase
{
    public function test_it_stores_server_action_information(): void
    {
        $action = new CloudServerActionData(
            action: 'power_off',
            message: 'Power-off operation is available.',
            startedAt: '2026-08-05T19:00:00Z',
        );

        self::assertSame(
            'power_off',
            $action->action,
        );

        self::assertSame(
            'Power-off operation is available.',
            $action->message,
        );

        self::assertSame(
            '2026-08-05T19:00:00Z',
            $action->startedAt,
        );
    }

    public function test_optional_values_default_to_null(): void
    {
        $action = new CloudServerActionData(
            action: 'power_on',
        );

        self::assertSame(
            'power_on',
            $action->action,
        );

        self::assertNull($action->message);
        self::assertNull($action->startedAt);
    }
}
