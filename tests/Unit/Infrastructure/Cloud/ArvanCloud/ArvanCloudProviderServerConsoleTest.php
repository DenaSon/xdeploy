<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudProviderServerConsoleTest extends TestCase
{
    private const string BASE_URL = 'https://napi.arvancloud.ir/ecc/v1';

    private const string REGION_ID = 'eu-west1-a';

    private const string SERVER_ID =
        '826bb07a-dd60-4229-841a-6ebe9fcbbd13';

    private const string CONSOLE_URL =
        'https://console.arvaniaas.test/cluster/vnc_lite.html?token=test-token';

    public function test_provider_implements_server_console_contract(): void
    {
        $this->assertInstanceOf(
            CloudServerConsoleInterface::class,
            $this->provider(),
        );
    }

    public function test_it_gets_a_fresh_vnc_console_url(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            self::BASE_URL
            .'/regions/'.self::REGION_ID
            .'/servers/'.self::SERVER_ID
            .'/vnc' => Http::response(
                [
                    'data' => [
                        'url' => self::CONSOLE_URL,
                    ],
                ],
                200,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        ]);

        $console = $this->provider()->getVncConsole(
            region: self::REGION_ID,
            serverId: self::SERVER_ID,
        );

        $this->assertSame(
            self::CONSOLE_URL,
            $console->url,
        );

        Http::assertSent(
            static fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::BASE_URL
                .'/regions/'.self::REGION_ID
                .'/servers/'.self::SERVER_ID
                .'/vnc',
        );

        Http::assertSentCount(1);
    }

    public function test_it_normalizes_the_server_reference(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            self::BASE_URL
            .'/regions/'.self::REGION_ID
            .'/servers/'.self::SERVER_ID
            .'/vnc' => Http::response(
                [
                    'data' => [
                        'url' => self::CONSOLE_URL,
                    ],
                ],
                200,
            ),
        ]);

        $console = $this->provider()->getVncConsole(
            region: ' '.self::REGION_ID.' ',
            serverId: ' '.self::SERVER_ID.' ',
        );

        $this->assertSame(
            self::CONSOLE_URL,
            $console->url,
        );

        Http::assertSentCount(1);
    }

    #[DataProvider('invalidServerReferenceProvider')]
    public function test_it_rejects_an_invalid_server_reference_before_request(
        string $region,
        string $serverId,
    ): void {
        Http::preventStrayRequests();
        Http::fake();

        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->getVncConsole(
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
            self::SERVER_ID,
        ];

        yield 'invalid region' => [
            'eu/west',
            self::SERVER_ID,
        ];

        yield 'empty server id' => [
            self::REGION_ID,
            '',
        ];

        yield 'invalid server id' => [
            self::REGION_ID,
            'server/id',
        ];

        yield 'server id contains control character' => [
            self::REGION_ID,
            self::SERVER_ID."\0",
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
