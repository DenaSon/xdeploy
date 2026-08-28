<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Billing;

use App\Domain\Cloud\Enums\CloudProviderType;
use App\Support\Billing\CustomerOrderPresentation;
use PHPUnit\Framework\TestCase;

final class CustomerOrderPresentationTest extends TestCase
{
    public function test_every_cloud_provider_has_an_order_presentation_label(): void
    {
        $expected = [
            CloudProviderType::Arvan->value => 'ابر آروان',
            CloudProviderType::Liara->value => 'لیارا',
            CloudProviderType::ParsPack->value => 'پارس‌پک',
        ];

        self::assertCount(count(CloudProviderType::cases()), $expected);

        foreach (CloudProviderType::cases() as $provider) {
            self::assertSame(
                $expected[$provider->value],
                CustomerOrderPresentation::provider($provider),
            );
        }
    }
}
