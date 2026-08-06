<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudServerLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_provider_implements_server_lifecycle_contract(): void
    {
        $this->assertInstanceOf(
            CloudServerLifecycleInterface::class,
            $this->provider(),
        );
    }

    public function test_it_powers_on_a_server(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'Server powered on.',
            ]),
        ]);

        $this->provider()->powerOn(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/servers/server-123/power-on'
                && $request->data() === [],
        );

        Http::assertSentCount(1);
    }

    public function test_it_powers_off_a_server(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'Server powered off.',
            ]),
        ]);

        $this->provider()->powerOff(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/servers/server-123/power-off'
                && $request->data() === [],
        );

        Http::assertSentCount(1);
    }

    public function test_it_reboots_a_server_gracefully(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Server reboot accepted.',
                ],
                202,
            ),
        ]);

        $this->provider()->reboot(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/servers/server-123/reboot',
        );

        Http::assertSentCount(1);
    }

    public function test_it_deletes_a_server(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'Server Deleted',
            ]),
        ]);

        $this->provider()->deleteServer(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'DELETE'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/servers/server-123',
        );

        Http::assertSentCount(1);
    }

    public function test_it_maps_available_server_actions(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'action' => 'reboot',
                    'message' => 'Reboot operation is available.',
                    'start_time' => '2026-08-05T20:00:00Z',
                ],
                202,
            ),
        ]);

        $actions = $this->provider()
            ->getAvailableActions(
                region: 'eu-west1-a',
                serverId: 'server-123',
            );

        $this->assertCount(
            1,
            $actions,
        );

        $this->assertInstanceOf(
            CloudServerActionData::class,
            $actions[0],
        );

        $this->assertSame(
            'reboot',
            $actions[0]->action,
        );

        $this->assertSame(
            'Reboot operation is available.',
            $actions[0]->message,
        );

        $this->assertSame(
            '2026-08-05T20:00:00Z',
            $actions[0]->startedAt,
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/servers/server-123/actions',
        );

        Http::assertSentCount(1);
    }

    public function test_it_maps_a_list_of_available_actions(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    [
                        'action' => 'power_off',
                        'message' => null,
                        'start_time' => null,
                    ],
                    [
                        'action' => 'reboot',
                        'message' => 'Available.',
                        'start_time' => null,
                    ],
                ],
                202,
            ),
        ]);

        $actions = $this->provider()
            ->getAvailableActions(
                region: 'eu-west1-a',
                serverId: 'server-123',
            );

        $this->assertCount(
            2,
            $actions,
        );

        $this->assertSame(
            'power_off',
            $actions[0]->action,
        );

        $this->assertSame(
            'reboot',
            $actions[1]->action,
        );
    }

    public function test_it_rejects_invalid_actions_response(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'action' => [],
                    'message' => 'Invalid action.',
                ],
                202,
            ),
        ]);

        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->provider()->getAvailableActions(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );
    }

    #[DataProvider('invalidServerIdProvider')]
    public function test_it_rejects_invalid_server_identifiers(
        string $serverId,
    ): void {
        Http::fake();

        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->powerOn(
                region: 'eu-west1-a',
                serverId: $serverId,
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidServerIdProvider(): array
    {
        return [
            'empty' => [
                '',
            ],

            'whitespace' => [
                '   ',
            ],

            'path traversal' => [
                '../server-123',
            ],

            'slash' => [
                'server/123',
            ],

            'query string' => [
                'server-123?force=true',
            ],

            'fragment' => [
                'server-123#action',
            ],

            'absolute URL' => [
                'https://example.test/server',
            ],

            'control character' => [
                "server-123\n",
            ],
        ];
    }

    private function provider(): ArvanCloudProvider
    {
        return new ArvanCloudProvider(
            client: new ArvanCloudClient(
                baseUrl: 'https://api.example.test/ecc/v1',
                apiKey: 'test-api-key',
                connectTimeout: 5,
                requestTimeout: 15,
            ),
            mapper: new ArvanCloudResponseMapper,
        );
    }
}
