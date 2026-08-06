<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Servers;

use App\Application\Cloud\Servers\GetCloudServerActionsAction;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use PHPUnit\Framework\TestCase;

final class GetCloudServerActionsActionTest extends TestCase
{
    public function test_it_returns_available_server_actions(): void
    {
        $expectedActions = [
            new CloudServerActionData(
                action: 'power_off',
                message: 'Power off is available.',
                startedAt: null,
            ),

            new CloudServerActionData(
                action: 'reboot',
                message: 'Reboot is available.',
                startedAt: null,
            ),
        ];

        $lifecycle = $this->createMock(
            CloudServerLifecycleInterface::class,
        );

        $lifecycle
            ->expects($this->once())
            ->method('getAvailableActions')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willReturn(
                $expectedActions,
            );

        $action = new GetCloudServerActionsAction(
            lifecycle: $lifecycle,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertSame(
            $expectedActions,
            $result,
        );

        $this->assertCount(
            2,
            $result,
        );

        $this->assertSame(
            'power_off',
            $result[0]->action,
        );

        $this->assertSame(
            'reboot',
            $result[1]->action,
        );
    }

    public function test_it_returns_an_empty_list_when_no_action_is_available(): void
    {
        $lifecycle = $this->createMock(
            CloudServerLifecycleInterface::class,
        );

        $lifecycle
            ->expects($this->once())
            ->method('getAvailableActions')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willReturn([]);

        $action = new GetCloudServerActionsAction(
            lifecycle: $lifecycle,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertSame(
            [],
            $result,
        );
    }

    public function test_it_does_not_hide_provider_exceptions(): void
    {
        $lifecycle = $this->createMock(
            CloudServerLifecycleInterface::class,
        );

        $lifecycle
            ->expects($this->once())
            ->method('getAvailableActions')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willThrowException(
                new CloudConnectionException(
                    'Cloud provider is temporarily unavailable.',
                ),
            );

        $action = new GetCloudServerActionsAction(
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
