<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Billing;

use App\Domain\Cloud\Enums\CloudProviderType;
use App\Support\Billing\CustomerOrderPresentation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomerOrderPresentationTest extends TestCase
{
    #[DataProvider('providerPresentationProvider')]
    public function test_every_cloud_provider_uses_its_public_order_presentation_label(
        CloudProviderType $provider,
        string $expectedLabel,
    ): void {
        self::assertSame(
            $expectedLabel,
            CustomerOrderPresentation::provider($provider),
        );
    }

    /**
     * @return iterable<string, array{CloudProviderType, string}>
     */
    public static function providerPresentationProvider(): iterable
    {
        yield 'arvan' => [
            CloudProviderType::Arvan,
            'Core-1',
        ];

        yield 'liara' => [
            CloudProviderType::Liara,
            'دماوند',
        ];

        yield 'parspack' => [
            CloudProviderType::ParsPack,
            'زاگرس',
        ];
    }
}
