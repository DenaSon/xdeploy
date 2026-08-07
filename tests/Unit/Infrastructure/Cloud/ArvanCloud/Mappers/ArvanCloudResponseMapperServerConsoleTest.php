<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudResponseMapperServerConsoleTest extends TestCase
{
    private const string CONSOLE_URL =
        'https://console.arvaniaas.test/cluster/vnc_lite.html?token=test-token';

    public function test_it_maps_the_verified_vnc_response(): void
    {
        $console = (new ArvanCloudResponseMapper)->mapServerVnc(
            payload: [
                'data' => [
                    'url' => self::CONSOLE_URL,
                ],
            ],
        );

        $this->assertSame(
            self::CONSOLE_URL,
            $console->url,
        );
    }

    public function test_it_maps_the_direct_openapi_response(): void
    {
        $console = (new ArvanCloudResponseMapper)->mapServerVnc(
            payload: [
                'url' => self::CONSOLE_URL,
            ],
        );

        $this->assertSame(
            self::CONSOLE_URL,
            $console->url,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    #[DataProvider('invalidVncPayloadProvider')]
    public function test_it_rejects_invalid_vnc_payloads(
        array $payload,
    ): void {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        (new ArvanCloudResponseMapper)->mapServerVnc(
            payload: $payload,
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidVncPayloadProvider(): iterable
    {
        yield 'empty response' => [
            [],
        ];

        yield 'invalid data envelope type' => [
            [
                'data' => 'invalid',
            ],
        ];

        yield 'data envelope is a list' => [
            [
                'data' => [
                    [
                        'url' => self::CONSOLE_URL,
                    ],
                ],
            ],
        ];

        yield 'missing url' => [
            [
                'data' => [
                    'message' => 'missing URL',
                ],
            ],
        ];

        yield 'null url' => [
            [
                'data' => [
                    'url' => null,
                ],
            ],
        ];

        yield 'empty url' => [
            [
                'data' => [
                    'url' => '   ',
                ],
            ],
        ];

        yield 'non-string url' => [
            [
                'data' => [
                    'url' => 123,
                ],
            ],
        ];

        yield 'relative url' => [
            [
                'data' => [
                    'url' => '/vnc_lite.html?token=test-token',
                ],
            ],
        ];

        yield 'insecure http url' => [
            [
                'data' => [
                    'url' => 'http://console.example.test/vnc_lite.html?token=test-token',
                ],
            ],
        ];

        yield 'url without a host' => [
            [
                'data' => [
                    'url' => 'https:///vnc_lite.html?token=test-token',
                ],
            ],
        ];
    }
}
