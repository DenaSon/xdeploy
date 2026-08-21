<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Liara;

use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\Enums\CloudServerPowerState;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use App\Infrastructure\Cloud\Liara\LiaraCloudProvider;
use App\Infrastructure\Cloud\Liara\Mappers\LiaraCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LiaraCloudProviderTest extends TestCase
{
    private const string BASE_URL = 'https://iaas-api.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_reads_catalog_from_liara_endpoints(): void
    {
        Http::fake([
            self::BASE_URL.'/plans' => Http::response(
                $this->plansResponse(),
            ),
            self::BASE_URL.'/oss' => Http::response([
                'one-click-apps' => [
                    'docker' => [
                        '29.1.2',
                    ],
                    'gitlabce' => [
                        '18.6.1',
                    ],
                    'appwrite' => [
                        '1.8.0',
                    ],
                    'supabase' => [
                        '2025.12.17',
                    ],
                    'sentry' => [
                        '25.12.0',
                    ],
                    'jitsi' => [
                        'stable-11146',
                    ],
                ],
                'ubuntu' => [
                    '24.04',
                    '22.04',
                ],
                'debian' => [
                    '12.9',
                ],
                'windowsserver' => [
                    '2025',
                ],
            ]),
        ]);

        $provider = $this->provider();

        $regions = $provider->listRegions();
        $sizes = $provider->listSizes('iran');
        $images = $provider->listImages('iran');
        $imageIds = array_map(
            static fn ($image): string => $image->id,
            $images,
        );

        $this->assertSame('iran', $regions[0]->id);
        $this->assertSame('standard-base-g2', $sizes[0]->id);
        $this->assertContains('ubuntu-24.04', $imageIds);
        $this->assertContains('debian-12.9', $imageIds);
        $this->assertContains('windowsserver-2025', $imageIds);
        $this->assertContains('docker-29.1.2', $imageIds);
        $this->assertContains('jitsi-stable-11146', $imageIds);
        $this->assertSame('10500000', $sizes[0]->monthlyPrice?->amount);

        Http::assertSentCount(3);
    }

    public function test_it_reads_purchase_catalog_from_liara_endpoints(): void
    {
        Http::fake([
            self::BASE_URL.'/plans' => Http::response(
                $this->plansResponse(),
            ),
            self::BASE_URL.'/oss' => Http::response([
                'ubuntu' => [
                    '24.04',
                ],
            ]),
        ]);

        $provider = $this->provider();

        $this->assertNotEmpty(
            $provider->listPurchaseRegions(),
        );
        $this->assertNotEmpty(
            $provider->listPurchaseSizes('iran'),
        );
        $this->assertNotEmpty(
            $provider->listPurchaseImages('iran'),
        );

        Http::assertSentCount(3);
    }

    public function test_it_creates_server_using_verified_liara_payload(): void
    {
        Http::fake([
            self::BASE_URL.'/plans' => Http::response(
                $this->plansResponse(),
            ),
            self::BASE_URL.'/vm' => Http::response([
                'taskID' => '6a80c1906727f3d124d794cb',
                'VMID' => '6a80c18f6727f3d124d794c6',
            ]),
        ]);

        $created = $this->provider()->createServer(
            $this->createData(),
        );

        $this->assertSame(
            '6a80c18f6727f3d124d794c6',
            $created->id,
        );
        $this->assertSame('root', $created->username);
        $this->assertFalse($created->hasGeneratedPassword());

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === self::BASE_URL.'/vm'
                && $request->data() === [
                    'name' => 'cf-liara-test',
                    'OS' => 'ubuntu-24.04',
                    'plan' => 'standard-base-g2',
                    'config' => [
                        'SSHKeys' => [],
                    ],
                ],
        );
    }

    public function test_it_rejects_arvan_shaped_networking_during_liara_create(): void
    {
        $data = new CreateCloudServerData(
            name: 'cf-liara-test',
            regionId: 'iran',
            sizeId: 'standard-base-g2',
            imageId: 'ubuntu-24.04',
            diskGiB: 20,
            networkId: 'network-id',
            securityGroupIds: [
                'security-group-id',
            ],
        );

        $this->expectException(
            CloudValidationException::class,
        );

        $this->provider()->createServer($data);
    }

    public function test_it_rejects_custom_disk_entitlement_in_current_create_adapter(): void
    {
        Http::fake([
            self::BASE_URL.'/plans' => Http::response(
                $this->plansResponse(),
            ),
        ]);

        $data = new CreateCloudServerData(
            name: 'cf-liara-test',
            regionId: 'iran',
            sizeId: 'standard-base-g2',
            imageId: 'ubuntu-24.04',
            diskGiB: 25,
        );

        $this->expectException(
            CloudValidationException::class,
        );

        $this->provider()->createServer($data);
    }

    public function test_it_reads_vm_details_with_async_credential(): void
    {
        Http::fake([
            self::BASE_URL.'/vm/*' => Http::response(
                $this->vmDetails(),
            ),
        ]);

        $server = $this->provider()->findServer(
            region: 'iran',
            serverId: '6a80c18f6727f3d124d794c6',
        );

        $this->assertSame('46.34.163.219', $server->firstPublicIpv4());
        $this->assertSame('root-password', $server->generatedPassword());
        $this->assertSame(
            CloudServerPowerState::Running,
            $server->powerState,
        );
    }

    public function test_it_uses_patch_power_endpoint_for_lifecycle_actions(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response('', 200),
        ]);

        $provider = $this->provider();
        $serverId = '6a80c18f6727f3d124d794c6';

        $provider->powerOn('iran', $serverId);
        $provider->powerOff('iran', $serverId);
        $provider->reboot('iran', $serverId);

        foreach ([
            'start',
            'stop',
            'reboot',
        ] as $action) {
            Http::assertSent(
                fn (Request $request): bool => $request->method() === 'PATCH'
                    && $request->url() === self::BASE_URL
                    .'/vm/power/'.$serverId
                    && $request->data() === [
                        'action' => $action,
                    ],
            );
        }

        Http::assertSentCount(3);
    }

    public function test_it_deletes_vm_using_provider_resource_id(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response('', 200),
        ]);

        $this->provider()->deleteServer(
            region: 'iran',
            serverId: '6a80c18f6727f3d124d794c6',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'DELETE'
                && $request->url() === self::BASE_URL
                .'/vm/6a80c18f6727f3d124d794c6',
        );
    }

    public function test_it_resets_root_password_without_sending_a_body(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'password' => 'new-root-password',
            ]),
        ]);

        $reset = $this->provider()->resetRootPassword(
            region: 'iran',
            serverId: '6a80c18f6727f3d124d794c6',
        );

        $this->assertSame('new-root-password', $reset->password);

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === self::BASE_URL
                .'/vm/reset-password/6a80c18f6727f3d124d794c6'
                && $request->data() === [],
        );
    }

    public function test_it_derives_available_lifecycle_actions_from_power_state(): void
    {
        Http::fake([
            self::BASE_URL.'/vm/*' => Http::response(
                $this->vmDetails(),
            ),
        ]);

        $actions = $this->provider()->getAvailableActions(
            region: 'iran',
            serverId: '6a80c18f6727f3d124d794c6',
        );

        $this->assertSame(
            [
                'power-off',
                'reboot',
            ],
            array_map(
                static fn ($action): string => $action->action,
                $actions,
            ),
        );
    }

    public function test_resize_catalog_is_truthful_about_default_storage_only(): void
    {
        Http::fake([
            self::BASE_URL.'/plans' => Http::response(
                $this->plansResponse(),
            ),
        ]);

        $provider = $this->provider();

        $size = $provider->calculateSize(
            region: 'iran',
            sizeId: 'standard-base-g2',
            diskGiB: 20,
        );

        $diskPrice = $provider->calculateDiskPrice(
            region: 'iran',
            sizeId: 'standard-base-g2',
            diskGiB: 20,
        );

        $this->assertSame(20, $size->diskGiB);
        $this->assertSame('0', $diskPrice->hourlyPrice->amount);
        $this->assertSame('0', $diskPrice->monthlyPrice->amount);
    }

    public function test_resize_catalog_rejects_unverified_custom_disk_pricing(): void
    {
        Http::fake([
            self::BASE_URL.'/plans' => Http::response(
                $this->plansResponse(),
            ),
        ]);

        $this->expectException(
            CloudValidationException::class,
        );

        $this->provider()->calculateDiskPrice(
            region: 'iran',
            sizeId: 'standard-base-g2',
            diskGiB: 25,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function plansResponse(): array
    {
        return [
            'plans' => [
                'standard-base-g2' => [
                    'available' => true,
                    'region' => 'iran',
                    'monthlyPrice' => 1_050_000,
                    'hourlyPrice' => 1458.33,
                    'volume' => 20,
                    'RAM' => [
                        'amount' => 2,
                    ],
                    'CPU' => [
                        'amount' => 1,
                    ],
                    'IPv4MonthlyPrice' => [
                        200_000,
                        350_000,
                    ],
                    'IPv4HourlyPrice' => [
                        277.77,
                        486.11,
                    ],
                ],
            ],
        ];
    }

    private function createData(): CreateCloudServerData
    {
        return new CreateCloudServerData(
            name: 'cf-liara-test',
            regionId: 'iran',
            sizeId: 'standard-base-g2',
            imageId: 'ubuntu-24.04',
            diskGiB: 20,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function vmDetails(): array
    {
        return [
            '_id' => '6a80c18f6727f3d124d794c6',
            'plan' => 'standard-base-g2',
            'OS' => 'ubuntu-24.04',
            'state' => 'CREATED',
            'config' => [
                'SSHKeys' => [],
                'hostname' => 'ubuntu-cf-liara-test',
                'rootPassword' => 'root-password',
            ],
            'name' => 'cf-liara-test',
            'guestCus' => [
                'status' => 'SUCCEEDED',
            ],
            'createdAt' => '2026-08-15T19:44:15.993Z',
            'IPs' => [
                [
                    'address' => '46.34.163.219',
                    'version' => 'v4',
                ],
            ],
            'power' => 'POWERED_ON',
            'guestState' => 'RUNNING',
            'planDetails' => [
                'available' => true,
                'region' => 'iran',
                'monthlyPrice' => 1_050_000,
                'hourlyPrice' => 1458.33,
                'volume' => 20,
                'RAM' => [
                    'amount' => 2,
                ],
                'CPU' => [
                    'amount' => 1,
                ],
            ],
        ];
    }

    private function provider(): LiaraCloudProvider
    {
        return new LiaraCloudProvider(
            client: new LiaraCloudClient(
                baseUrl: self::BASE_URL,
                apiToken: 'test-token',
                connectTimeout: 5,
                requestTimeout: 15,
            ),
            mapper: new LiaraCloudResponseMapper,
        );
    }
}
