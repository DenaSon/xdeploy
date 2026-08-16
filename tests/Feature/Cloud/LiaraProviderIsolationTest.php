<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LiaraProviderIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_liara_provider_can_be_resolved_without_relying_on_arvan_runtime_configuration(): void
    {
        config()->set('cloud.providers.arvan.api_key', null);
        config()->set('cloud.providers.liara.api_token', 'test-liara-token');

        $registry = $this->app->make(CloudProviderRegistryInterface::class);

        $this->assertContains(
            CloudProviderType::Liara,
            $registry->registeredProviders(),
        );
    }
}
