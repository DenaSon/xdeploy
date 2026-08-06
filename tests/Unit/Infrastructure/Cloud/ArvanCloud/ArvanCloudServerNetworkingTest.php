<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\DTOs\CloudPortData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudServerNetworkingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_provider_implements_networking_contract(): void
    {
        $this->assertInstanceOf(
            CloudServerNetworkingInterface::class,
            $this->provider(),
        );
    }

    public function test_it_lists_only_ports_belonging_to_server(): void
    {
        Http::fake([
            '*' => Http::response([
                [
                    'id' => 'port-123',
                    'instance_id' => 'server-123',
                    'ips' => [
                        '203.0.113.10',
                    ],
                    'mac_address' => 'fa:16:3e:00:00:01',
                    'network_id' => 'network-123',
                    'port_security_enabled' => true,
                    'security_group_ids' => [
                        'security-group-123',
                    ],
                    'status' => 'ACTIVE',
                ],
                [
                    'id' => 'port-456',
                    'instance_id' => 'another-server',
                    'ips' => [
                        '203.0.113.20',
                    ],
                    'mac_address' => 'fa:16:3e:00:00:02',
                    'network_id' => 'network-456',
                    'port_security_enabled' => true,
                    'security_group_ids' => [],
                    'status' => 'ACTIVE',
                ],
            ]),
        ]);

        $ports = $this->provider()->listServerPorts(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertCount(
            1,
            $ports,
        );

        $this->assertInstanceOf(
            CloudPortData::class,
            $ports[0],
        );

        $this->assertSame(
            'port-123',
            $ports[0]->id,
        );

        $this->assertSame(
            'server-123',
            $ports[0]->serverId,
        );

        $this->assertSame(
            [
                '203.0.113.10',
            ],
            $ports[0]->ips,
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/ports',
        );

        Http::assertSentCount(1);
    }

    public function test_it_adds_an_ipv4_with_security_groups(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Public IP added.',
                ],
                201,
            ),
        ]);

        $this->provider()->addPublicIp(
            region: 'eu-west1-a',
            serverId: 'server-123',
            version: CloudIpVersion::IPv4,
            securityGroupIds: [
                'security-group-123',
                'security-group-456',
            ],
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'POST'
                    && $request->url() ===
                    'https://api.example.test/ecc/v1/regions/eu-west1-a/servers/server-123/add-public-ip'
                    && $request->data() === [
                        'type' => 'ipv4',
                        'security_groups' => [
                            'security-group-123',
                            'security-group-456',
                        ],
                    ];
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_adds_an_ipv6_without_security_groups(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'message' => 'Public IP added.',
                ],
                201,
            ),
        ]);

        $this->provider()->addPublicIp(
            region: 'eu-west1-a',
            serverId: 'server-123',
            version: CloudIpVersion::IPv6,
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->data() === [
                    'type' => 'ipv6',
                ],
        );

        Http::assertSentCount(1);
    }

    public function test_it_deletes_a_port(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'Port deleted.',
            ]),
        ]);

        $this->provider()->deletePort(
            region: 'eu-west1-a',
            portId: 'port-123',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'DELETE'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/ports/port-123',
        );

        Http::assertSentCount(1);
    }

    #[DataProvider('invalidIdentifierProvider')]
    public function test_it_rejects_invalid_server_identifiers(
        string $serverId,
    ): void {
        Http::fake();

        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->listServerPorts(
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
    public static function invalidIdentifierProvider(): array
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
                'server-123?test=true',
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

            createType: 'cinder',

            defaultUsername: 'ubuntu',
        );
    }
}
