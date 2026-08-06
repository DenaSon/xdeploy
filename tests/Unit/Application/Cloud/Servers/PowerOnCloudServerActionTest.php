<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Servers;

use App\Application\Cloud\Servers\PowerOnCloudServerAction;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use PHPUnit\Framework\TestCase;

final class PowerOnCloudServerActionTest extends TestCase
{
    public function test_it_delegates_power_on_to_lifecycle_provider(): void
    {
        $lifecycle = $this->createMock(
            CloudServerLifecycleInterface::class,
        );

        $lifecycle
            ->expects($this->once())
            ->method('powerOn')
            ->with(
                'eu-west1-a',
                'server-123',
            );

        $action = new PowerOnCloudServerAction(
            lifecycle: $lifecycle,
        );

        $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );
    }

    public function test_it_does_not_hide_provider_exceptions(): void
    {
        $lifecycle = $this->createMock(
            CloudServerLifecycleInterface::class,
        );

        $lifecycle
            ->expects($this->once())
            ->method('powerOn')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willThrowException(
                new CloudConnectionException(
                    'Cloud provider is temporarily unavailable.',
                ),
            );

        $action = new PowerOnCloudServerAction(
            lifecycle: $lifecycle,
        );

        $this->expectException(
            CloudConnectionException::class,
        );

        $this->expectExceptionMessage(
            'Cloud provider is temporarily unavailable.',
        );

        $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );
    }
}
