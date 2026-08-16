<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use App\Infrastructure\Cloud\Liara\LiaraCloudProvider;
use Tests\TestCase;

final class LiaraProviderContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cloud.providers.liara.api_token', 'test-liara-token');
        $this->app->forgetInstance(LiaraCloudClient::class);
        $this->app->forgetInstance(LiaraCloudProvider::class);
    }

    public function test_liara_provider_does_not_expose_unsupported_console_capability(): void
    {
        $provider = $this->app->make(LiaraCloudProvider::class);

        self::assertInstanceOf(CloudServerLifecycleInterface::class, $provider);
        self::assertNotInstanceOf(CloudServerConsoleInterface::class, $provider);
        self::assertSame(CloudProviderType::Liara, $provider->type());
    }
}
