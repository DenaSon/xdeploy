<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudProviderServerInventoryTest extends TestCase
{
    private const string BASE_URL = 'https://napi.arvancloud.ir/ecc/v1';

    public function test_provider_implements_server_inventory_contract(): void
    {
        $this->assertInstanceOf(
            CloudServerInventoryInterface::class,
            $this->provider(),
        );
    }

    public function test_it_lists_servers_in_the_selected_region(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            self::BASE_URL
            .'/regions/eu-west1-a/servers' => Http::response(
                self::validResponse(),
                200,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        ]);

        $servers = $this->provider()->listServers(
            region: 'eu-west1-a',
        );

        $this->assertCount(
            1,
            $servers,
        );

        $this->assertSame(
            '93b31e1a-aa0b-4594-bf46-bcfef3ca8184',
            $servers[0]->id,
        );

        $this->assertSame(
            CloudServerStatus::Active,
            $servers[0]->status,
        );

        $this->assertSame(
            CloudServerPowerState::Running,
            $servers[0]->powerState,
        );

        Http::assertSent(
            static fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::BASE_URL
                .'/regions/eu-west1-a/servers',
        );

        Http::assertSentCount(1);
    }

    public function test_it_normalizes_the_region_identifier(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            self::BASE_URL
            .'/regions/eu-west1-a/servers' => Http::response(
                self::validResponse(),
                200,
            ),
        ]);

        $servers = $this->provider()->listServers(
            region: ' eu-west1-a ',
        );

        $this->assertSame(
            'eu-west1-a',
            $servers[0]->regionId,
        );

        Http::assertSent(
            static fn (Request $request): bool => $request->url()
                === self::BASE_URL
                .'/regions/eu-west1-a/servers',
        );

        Http::assertSentCount(1);
    }

    #[DataProvider('invalidRegionProvider')]
    public function test_it_rejects_invalid_regions_before_sending_a_request(
        string $region,
    ): void {
        Http::preventStrayRequests();
        Http::fake();

        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->listServers(
                region: $region,
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRegionProvider(): iterable
    {
        yield 'empty region' => [
            '',
        ];

        yield 'whitespace region' => [
            '   ',
        ];

        yield 'invalid region' => [
            'eu/west',
        ];

        yield 'control character' => [
            "eu-west1-a\0",
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

    /**
     * @return array<string, mixed>
     */
    private static function validResponse(): array
    {
        return [
            'data' => [
                [
                    'id' => '93b31e1a-aa0b-4594-bf46-bcfef3ca8184',
                    'name' => 'xdeploy-e2e-20260806-161430-5itccp',
                    'flavor' => [
                        'id' => 'eco-1-1-0',
                        'name' => 'eco-1-1-0',
                        'ram' => 1024,
                        'swap' => '',
                        'vcpus' => 1,
                        'disk' => 25,
                        'free_server' => false,
                    ],
                    'status' => 'ACTIVE',
                    'image' => [
                        'id' => '3236878e-2bdc-4cdd-b082-61b3eeb3f9df',
                        'name' => 'Ubuntu',
                        'os' => 'ubuntu',
                        'os_version' => '26.04',
                        'status' => 'active',
                        'username' => 'ubuntu',
                        'metadata' => [
                            'os_type' => 'linux',
                            'username' => 'ubuntu',
                        ],
                        'documents' => [],
                    ],
                    'created' => '2026-08-06T16:16:13Z',
                    'password' => '',
                    'task_state' => null,
                    'key_name' => '',
                    'ar_next' => '',
                    'security_groups' => [
                        [
                            'id' => '8449a4f5-5709-4017-9e63-45496bfe5cc9',
                            'description' => 'New default security group',
                            'name' => 'default',
                            'readonly' => true,
                            'default' => true,
                            'real_name' => 'arDefault',
                            'rules' => null,
                            'ip_addresses' => [
                                '185.204.168.143',
                            ],
                        ],
                    ],
                    'addresses' => [
                        'public203' => [
                            [
                                'mac_addr' => 'fa:16:3e:17:a8:55',
                                'version' => '4',
                                'addr' => '185.204.168.143',
                                'type' => 'fixed',
                                'is_public' => true,
                                'is_vpc' => false,
                            ],
                        ],
                    ],
                    'tags' => [],
                    'ha_enabled' => false,
                    'backup_enabled' => false,
                    'cluster_id' => '',
                    'spot' => false,
                    'networks' => [
                        '626ad7fd-3a62-4f3b-8908-7c0c3a91062d',
                    ],
                    'volume_backed' => true,
                    'vpcs' => null,
                    'domain' => '',
                ],
            ],
        ];
    }
}
