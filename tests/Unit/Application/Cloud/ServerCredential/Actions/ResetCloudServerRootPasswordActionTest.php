<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerCredential\Actions;

use App\Application\Cloud\ServerCredential\Actions\ResetCloudServerRootPasswordAction;
use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\DTOs\CloudRootPasswordResetData;
use Tests\TestCase;

final class ResetCloudServerRootPasswordActionTest extends TestCase
{
    public function test_it_resets_the_cloud_server_root_password(): void
    {
        $expectedResult = new CloudRootPasswordResetData(
            password: 'generated-password',
            message: 'Server Root password changed',
        );

        $credentialManager = $this->createMock(
            CloudServerCredentialManagerInterface::class,
        );

        $credentialManager
            ->expects($this->once())
            ->method('resetRootPassword')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willReturn(
                $expectedResult,
            );

        $action = new ResetCloudServerRootPasswordAction(
            credentialManager: $credentialManager,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertSame(
            $expectedResult,
            $result,
        );
    }

    public function test_it_passes_input_values_without_modification(): void
    {
        $expectedResult = new CloudRootPasswordResetData(
            password: 'generated-password',
            message: 'Server Root password changed',
        );

        $credentialManager = $this->createMock(
            CloudServerCredentialManagerInterface::class,
        );

        $credentialManager
            ->expects($this->once())
            ->method('resetRootPassword')
            ->with(
                ' eu-west1-a ',
                ' server-123 ',
            )
            ->willReturn(
                $expectedResult,
            );

        $action = new ResetCloudServerRootPasswordAction(
            credentialManager: $credentialManager,
        );

        $result = $action->handle(
            region: ' eu-west1-a ',
            serverId: ' server-123 ',
        );

        $this->assertSame(
            $expectedResult,
            $result,
        );
    }
}
