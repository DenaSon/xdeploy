<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Application\Cloud\Servers\PowerOnCloudServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CloudProviderRegistryStub;
use Tests\TestCase;

final class MultiProviderRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_liara_owned_server_does_not_use_default_arvan_provider_for_lifecycle(): void
    {
        config()->set('cloud.default', CloudProviderType::Arvan->value);

        $calls = [];

        $provider = new class($calls) implements CloudProviderInterface, CloudServerLifecycleInterface
        {
            public function __construct(private array &$calls) {}

            public function listRegions(): array { return []; }

            public function listSizes(string $region): array { return []; }

            public function listImages(string $region): array { return []; }

            public function powerOn(string $region, string $serverId): void
            {
                $this->calls[] = [$region, $serverId];
            }

            public function powerOff(string $region, string $serverId): void {}

            public function reboot(string $region, string $serverId): void {}

            public function delete(string $region, string $serverId): void {}
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

        $server = Server::factory()->create([
            'user_id' => User::factory()->create()->id,
            'cloud_provider' => CloudProviderType::Liara->value,
            'cloud_region' => 'iran',
            'cloud_server_id' => 'liara-vm-1',
        ]);

        app(PowerOnCloudServerAction::class)->handle($server);

        $this->assertSame([
            ['iran', 'liara-vm-1'],
        ], $calls);
    }
}
