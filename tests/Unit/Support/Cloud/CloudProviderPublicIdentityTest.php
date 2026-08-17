<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Cloud;

use App\Domain\Cloud\Enums\CloudProviderType;
use App\Support\Cloud\CloudProviderPublicIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CloudProviderPublicIdentityTest extends TestCase
{
    #[DataProvider('providerIdentityProvider')]
    public function test_public_identity_round_trips_to_canonical_provider(
        CloudProviderType $provider,
        string $code,
        string $label,
    ): void {
        self::assertSame(
            $code,
            CloudProviderPublicIdentity::code($provider),
        );

        self::assertSame(
            $label,
            CloudProviderPublicIdentity::label($provider),
        );

        self::assertSame(
            $provider,
            CloudProviderPublicIdentity::provider($code),
        );
    }

    public function test_unknown_public_code_does_not_resolve_to_a_provider(): void
    {
        self::assertNull(
            CloudProviderPublicIdentity::provider('core-999'),
        );
    }

    /**
     * @return iterable<string, array{CloudProviderType, string, string}>
     */
    public static function providerIdentityProvider(): iterable
    {
        yield 'arvan' => [
            CloudProviderType::Arvan,
            'core-1',
            'Core-1',
        ];

        yield 'liara' => [
            CloudProviderType::Liara,
            'core-2',
            'Core-2',
        ];
    }
}
