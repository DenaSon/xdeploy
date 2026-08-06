<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudResponseMapperRootPasswordResetTest extends TestCase
{
    public function test_it_maps_the_verified_root_password_reset_response(): void
    {
        $mapper = new ArvanCloudResponseMapper;

        $result = $mapper->mapRootPasswordReset([
            'data' => [
                'password' => 'generated-password',
            ],
            'message' => 'Server Root password changed',
        ]);

        $this->assertSame(
            'generated-password',
            $result->password,
        );

        $this->assertSame(
            'Server Root password changed',
            $result->message,
        );
    }

    public function test_it_preserves_the_generated_password_exactly_as_received(): void
    {
        $mapper = new ArvanCloudResponseMapper;

        $result = $mapper->mapRootPasswordReset([
            'data' => [
                'password' => ' generated-password ',
            ],
            'message' => 'Server Root password changed',
        ]);

        $this->assertSame(
            ' generated-password ',
            $result->password,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_it_rejects_invalid_root_password_reset_payloads(
        array $payload,
    ): void {
        $mapper = new ArvanCloudResponseMapper;

        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $mapper->mapRootPasswordReset(
            $payload,
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'missing data envelope' => [
            [
                'message' => 'Server Root password changed',
            ],
        ];

        yield 'data is not an object' => [
            [
                'data' => 'invalid',
                'message' => 'Server Root password changed',
            ],
        ];

        yield 'data is a list' => [
            [
                'data' => [
                    [
                        'password' => 'generated-password',
                    ],
                ],
                'message' => 'Server Root password changed',
            ],
        ];

        yield 'missing password' => [
            [
                'data' => [],
                'message' => 'Server Root password changed',
            ],
        ];

        yield 'password is not a string' => [
            [
                'data' => [
                    'password' => 123456,
                ],
                'message' => 'Server Root password changed',
            ],
        ];

        yield 'password is empty' => [
            [
                'data' => [
                    'password' => '',
                ],
                'message' => 'Server Root password changed',
            ],
        ];

        yield 'password contains only whitespace' => [
            [
                'data' => [
                    'password' => '   ',
                ],
                'message' => 'Server Root password changed',
            ],
        ];

        yield 'missing message' => [
            [
                'data' => [
                    'password' => 'generated-password',
                ],
            ],
        ];

        yield 'message is not a string' => [
            [
                'data' => [
                    'password' => 'generated-password',
                ],
                'message' => [
                    'invalid',
                ],
            ],
        ];

        yield 'message is empty' => [
            [
                'data' => [
                    'password' => 'generated-password',
                ],
                'message' => '',
            ],
        ];
    }
}
