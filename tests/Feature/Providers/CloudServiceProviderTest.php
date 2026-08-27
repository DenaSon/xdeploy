<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudVolumeManager;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use Tests\TestCase;

final class CloudServiceProviderTest extends TestCase
{
    public function test_arvan_volume_manager_is_resolvable_as_provider_capability(): void
    {
        config()->set('cloud.default', CloudProviderType::Arvan->value);
        config()->set('cloud.providers.arvan.enabled', true);
        config()->set('cloud.providers.arvan.purchase_enabled', false);
        config()->set(
            'cloud.providers.arvan.base_url',
            'https://napi.arvancloud.ir/ecc/v1',
        );
        config()->set('cloud.providers.arvan.api_key', 'test-api-key');
        config()->set('cloud.providers.liara.enabled', false);
        config()->set('cloud.providers.liara.purchase_enabled', false);

        $this->app->forgetInstance(CloudProviderRegistry::class);
        $this->app->forgetInstance(CloudProviderRegistryInterface::class);
        $this->app->forgetInstance(CloudVolumeManagerInterface::class);
        $this->app->forgetInstance(ArvanCloudVolumeManager::class);

        $registry = $this->app->make(
            CloudProviderRegistryInterface::class,
        );

        $capability = $registry->resolveCapability(
            provider: CloudProviderType::Arvan,
            capability: CloudVolumeManagerInterface::class,
        );

        $this->assertInstanceOf(
            ArvanCloudVolumeManager::class,
            $capability,
        );

        $this->assertSame(
            $capability,
            $this->app->make(CloudVolumeManagerInterface::class),
        );
    }
}
