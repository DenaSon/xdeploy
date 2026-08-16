<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\Liara\LiaraCloudProvider;
use Tests\TestCase;

final class LiaraProviderContractTest extends TestCase
{
    public function test_liara_provider_does_not_expose_unsupported_console_capability(): void
    {
        $provider = app(LiaraCloudProvider::class);

        self::assertInstanceOf(
            CloudServerLifecycleInterface::class,
            $provider,
        );

        self::assertSame(
            CloudProviderType::Liara->value,
            $provider->type()->value,
        );
    }
}
