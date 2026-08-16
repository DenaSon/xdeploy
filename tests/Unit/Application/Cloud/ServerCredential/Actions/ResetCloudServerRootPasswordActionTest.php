<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerCredential\Actions;

use App\Application\Cloud\ServerCredential\Actions\ResetCloudServerRootPasswordAction;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\DTOs\CloudRootPasswordResetData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use Tests\TestCase;

final class ResetCloudServerRootPasswordActionTest extends TestCase
{
    public function test_it_routes_password_reset_to_the_servers_owning_provider(): void
    {
        $expected = new CloudRootPasswordResetData(
            password: 'generated-password',
            message: 'Server Root password changed',
        );

        $manager = $this->createMock(
            CloudServerCredentialManagerInterface::class,
        );

        $manager->expects($this->once())
            ->method('resetRootPassword')
            ->with('iran', 'liara-vm-123')
            ->willReturn($expected);

        $providers = $this->createMock(
            CloudProviderRegistryInterface::class,
        );

        $providers->expects($this->once())
            ->method('resolveCapability')
            ->with(
                CloudProviderType::Liara,
                CloudServerCredentialManagerInterface::class,
            )
            ->willReturn($manager);

        $server = new Server();
        $server->forceFill([
            'cloud_provider' => 'liara',
            'cloud_region' => 'iran',
            'cloud_server_id' => 'liara-vm-123',
        ]);

        $result = (new ResetCloudServerRootPasswordAction(
            capabilities: new CloudServerCapabilityResolver(
                providers: $providers,
            ),
        ))->handle($server);

        $this->assertSame($expected, $result);
    }
}
