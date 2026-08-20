<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\WordPress\PublicEndpoint\DTOs;

use App\Infrastructure\Application\WordPress\PublicEndpoint\DTOs\WordPressRuntimeConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WordPressRuntimeConfigurationTest extends TestCase
{
    public function test_it_recognizes_the_managed_https_url(): void
    {
        $configuration = new WordPressRuntimeConfiguration(
            publicUrl: 'https://blog.example.com',
        );

        self::assertTrue($configuration->hasPublicConfiguration());
        self::assertSame('blog.example.com', $configuration->domain());
        self::assertTrue($configuration->matches('blog.example.com'));
    }

    #[DataProvider('unsupportedUrls')]
    public function test_it_rejects_urls_outside_the_managed_origin_shape(
        string $url,
    ): void {
        $configuration = new WordPressRuntimeConfiguration(
            publicUrl: $url,
        );

        self::assertTrue($configuration->hasPublicConfiguration());
        self::assertNull($configuration->domain());
        self::assertFalse($configuration->matches('blog.example.com'));
    }

    /** @return array<string, array{string}> */
    public static function unsupportedUrls(): array
    {
        return [
            'plain HTTP' => ['http://blog.example.com'],
            'custom port' => ['https://blog.example.com:8443'],
            'path' => ['https://blog.example.com/news'],
            'query string' => ['https://blog.example.com/?preview=1'],
            'fragment' => ['https://blog.example.com/#top'],
            'localhost' => ['https://localhost'],
            'IP address' => ['https://192.0.2.10'],
            'invalid value' => ['not-a-url'],
        ];
    }

    public function test_empty_runtime_configuration_is_private(): void
    {
        $configuration = new WordPressRuntimeConfiguration(
            publicUrl: null,
        );

        self::assertFalse($configuration->hasPublicConfiguration());
        self::assertNull($configuration->domain());
        self::assertFalse($configuration->matches('blog.example.com'));
    }
}
