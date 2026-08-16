<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use App\Infrastructure\Cloud\Liara\LiaraCloudProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LiaraProviderIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_liara_provider_can_be_resolved_without_relying_on_arvan_runtime_configuration(): void
    {
        config()->set('cloud.default', CloudProviderType::Liara->value);
        config()->set('cloud.providers.arvan.api_key', null);
        config()->set('cloud.providers.liara.api_token', 'test-liara-token');

        foreach ([
            CloudProviderRegistry::class,
            CloudProviderRegistryInterface::class,
            LiaraCloudClient::class,
            LiaraCloudProvider::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertSame(
            [CloudProviderType::Liara],
            $registry->registeredProviders(),
        );
        $this->assertInstanceOf(
            LiaraCloudProvider::class,
            $registry->resolve(CloudProviderType::Liara),
        );
    }
}
