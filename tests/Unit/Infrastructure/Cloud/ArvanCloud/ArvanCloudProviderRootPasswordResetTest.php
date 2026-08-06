<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudProviderRootPasswordResetTest extends TestCase
{
    private const string BASE_URL = 'https://napi.arvancloud.ir/ecc/v1';

    public function test_it_resets_the_root_password_without_a_request_body(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            self::BASE_URL
            .'/regions/eu-west1-a/servers/server-123/reset-root-password' => Http::response(
                [
                    'data' => [
                        'password' => 'generated-password',
                    ],
                    'message' => 'Server Root password changed',
                ],
                202,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        ]);

        $result = $this->provider()->resetRootPassword(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertSame(
            'generated-password',
            $result->password,
        );

        $this->assertSame(
            'Server Root password changed',
            $result->message,
        );

        Http::assertSent(
            static fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === self::BASE_URL
                .'/regions/eu-west1-a/servers/server-123/reset-root-password'
                && $request->body() === '',
        );

        Http::assertSentCount(1);
    }

    public function test_it_normalizes_the_region_and_server_identifier(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            self::BASE_URL
            .'/regions/eu-west1-a/servers/server-123/reset-root-password' => Http::response(
                [
                    'data' => [
                        'password' => 'generated-password',
                    ],
                    'message' => 'Server Root password changed',
                ],
                202,
            ),
        ]);

        $result = $this->provider()->resetRootPassword(
            region: ' eu-west1-a ',
            serverId: ' server-123 ',
        );

        $this->assertSame(
            'generated-password',
            $result->password,
        );

        Http::assertSent(
            static fn (Request $request): bool => $request->url()
                === self::BASE_URL
                .'/regions/eu-west1-a/servers/server-123/reset-root-password',
        );
    }

    #[DataProvider('invalidServerReferenceProvider')]
    public function test_it_rejects_invalid_server_references_before_sending_a_request(
        string $region,
        string $serverId,
    ): void {
        Http::preventStrayRequests();
        Http::fake();

        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->resetRootPassword(
                region: $region,
                serverId: $serverId,
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidServerReferenceProvider(): iterable
    {
        yield 'empty region' => [
            '',
            'server-123',
        ];

        yield 'whitespace region' => [
            '   ',
            'server-123',
        ];

        yield 'invalid region' => [
            'eu/west',
            'server-123',
        ];

        yield 'empty server identifier' => [
            'eu-west1-a',
            '',
        ];

        yield 'whitespace server identifier' => [
            'eu-west1-a',
            '   ',
        ];

        yield 'invalid server identifier' => [
            'eu-west1-a',
            'server/123',
        ];
    }

    private function provider(): ArvanCloudProvider
    {
        return new ArvanCloudProvider(
            client: new ArvanCloudClient(
                baseUrl: self::BASE_URL,
                apiKey: 'test-api-key',
            ),
            mapper: new ArvanCloudResponseMapper,
        );
    }
}
