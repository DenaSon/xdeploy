<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Billing;

use App\Domain\Cloud\Enums\CloudProviderType;
use App\Support\Billing\CustomerOrderPresentation;
use App\Support\Cloud\CloudProviderPublicIdentity;
use PHPUnit\Framework\TestCase;

final class CustomerOrderPresentationTest extends TestCase
{
    public function test_every_cloud_provider_uses_its_public_order_presentation_label(): void
    {
        foreach (CloudProviderType::cases() as $provider) {
            self::assertSame(
                CloudProviderPublicIdentity::label($provider),
                CustomerOrderPresentation::provider($provider),
            );
        }
    }
}
