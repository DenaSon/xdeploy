<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Application\Cloud\Servers\PowerOnCloudServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use Tests\Support\CloudProviderRegistryStub;
use Tests\TestCase;

final class MultiProviderRoutingTest extends TestCase
{
    public function test_liara_owned_server_does_not_use_default_arvan_provider_for_lifecycle(): void
    {
        config()->set('cloud.default', CloudProviderType::Arvan->value);

        $calls = new class
        {
            public array $items = [];
        };

        $provider = new class($calls) implements CloudProviderInterface, CloudServerLifecycleInterface
        {
            public function __construct(private object $calls) {}

            public function listRegions(): array
            {
                return [];
            }

            public function listSizes(string $region): array
            {
                return [];
            }

            public function listImages(string $region): array
            {
                return [];
            }

            public function powerOn(string $region, string $serverId): void
            {
                $this->calls->items[] = [$region, $serverId];
            }

            public function powerOff(string $region, string $serverId): void {}

            public function reboot(string $region, string $serverId): void {}

            public function deleteServer(string $region, string $serverId): void {}

            public function getAvailableActions(string $region, string $serverId): array
            {
                return [];
            }
        };

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $provider,
                capabilities: [
                    CloudServerLifecycleInterface::class => $provider,
                ],
                registeredProviders: [CloudProviderType::Liara],
            ),
        );

        $server = new Server([
            'cloud_provider' => CloudProviderType::Liara->value,
            'cloud_region' => 'iran',
            'cloud_server_id' => 'liara-vm-1',
        ]);

        app(PowerOnCloudServerAction::class)->handle($server);

        $this->assertSame([
            ['iran', 'liara-vm-1'],
        ], $calls->items);
    }
}
