<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerConsole\Actions;

use App\Application\Cloud\ServerConsole\Actions\GetCloudServerConsoleAction;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\DTOs\CloudServerConsoleData;
use Tests\TestCase;

final class GetCloudServerConsoleActionTest extends TestCase
{
    public function test_it_returns_a_fresh_server_console_url(): void
    {
        $region = 'eu-west1-a';
        $serverId = '826bb07a-dd60-4229-841a-6ebe9fcbbd13';

        $expected = new CloudServerConsoleData(
            url: 'https://console.arvaniaas.test/cluster/vnc_lite.html?token=test-token',
        );

        $console = $this->createMock(
            CloudServerConsoleInterface::class,
        );

        $console->expects($this->once())
            ->method('getVncConsole')
            ->with(
                $region,
                $serverId,
            )
            ->willReturn($expected);

        $result = (new GetCloudServerConsoleAction(
            console: $console,
        ))->execute(
            region: $region,
            serverId: $serverId,
        );

        $this->assertSame(
            $expected,
            $result,
        );
    }
}
