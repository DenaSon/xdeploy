<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Cloud\Liara;

use App\Application\Billing\Actions\CreateOrderAction;
use App\Application\Billing\Actions\ProvisionPaidOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use App\Infrastructure\Cloud\Liara\LiaraCloudProvider;
use App\Infrastructure\Cloud\Liara\Mappers\LiaraCloudResponseMapper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LiaraPaidOrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private const string BASE_URL = 'https://iaas-api.example.test';

    private const string VM_ID = '6a80c18f6727f3d124d794c6';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set('cloud.default', CloudProviderType::Liara->value);
        config()->set('cloud.providers.liara.defaults.init_script', '');
        config()->set('cloud.providers.liara.defaults.ha_enabled', false);

        $provider = new LiaraCloudProvider(
            client: new LiaraCloudClient(
                baseUrl: self::BASE_URL,
                apiToken: 'test-liara-token',
                connectTimeout: 5,
                requestTimeout: 15,
            ),
            mapper: new LiaraCloudResponseMapper(),
        );

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistry(
                providers: [
                    CloudProviderType::Liara->value => $provider,
                ],
            ),
        );
    }

    public function test_paid_liara_order_uses_frozen_provider_and_fulfills_through_real_adapter_boundary(): void
    {
        Http::fake([
            self::BASE_URL.'/plans' => Http::response(
                $this->plansResponse(),
            ),
            self::BASE_URL.'/oss' => Http::response([
                'ubuntu' => [
                    '24.04',
                    '22.04',
                ],
            ]),
            self::BASE_URL.'/vm' => Http::response([
                'taskID' => '6a80c1906727f3d124d794cb',
                'VMID' => self::VM_ID,
            ]),
            self::BASE_URL.'/vm/'.self::VM_ID => Http::response(
                $this->readyVmDetails(),
            ),
        ]);

        $user = User::factory()->create();

        $order = app(CreateOrderAction::class)->execute(
            user: $user,
            region: 'iran',
            sizeId: 'standard-base-g2',
            imageId: 'ubuntu-24.04',
            selectedDiskGiB: 20,
            period: '2_days',
            provider: CloudProviderType::Liara,
        );

        $this->assertSame(
            CloudProviderType::Liara,
            $order->cloud_provider,
        );
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame('iran', $order->region_id);
        $this->assertSame('standard-base-g2', $order->size_id);
        $this->assertSame('ubuntu-24.04', $order->image_id);
        $this->assertSame(20, $order->selected_disk_gib);

        $order->forceFill([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ])->saveOrFail();

        $server = app(ProvisionPaidOrderAction::class)->execute(
            $order->getKey(),
        );

        $freshOrder = $order->fresh();
        $freshServer = $server->fresh();

        $this->assertSame(OrderStatus::Fulfilled, $freshOrder->status);
        $this->assertSame($freshServer->getKey(), $freshOrder->server_id);

        $this->assertSame('liara', $freshServer->cloud_provider);
        $this->assertSame(self::VM_ID, $freshServer->cloud_server_id);
        $this->assertSame('iran', $freshServer->cloud_region);
        $this->assertSame('46.34.163.219', $freshServer->host);
        $this->assertSame('root', $freshServer->username);
        $this->assertTrue($freshServer->hasCredential());
        $this->assertSame(
            'generated-root-password',
            $freshServer->credential,
        );
        $this->assertNotNull($freshServer->provisioned_at);
        $this->assertNotNull($freshServer->expires_at);
        $this->assertSame(
            $freshOrder->duration_hours,
            (int) $freshServer->provisioned_at->diffInHours(
                $freshServer->expires_at,
            ),
        );

        $expectedServerName = $this->providerServerName($order->getKey());

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === self::BASE_URL.'/vm'
                && $request->data() === [
                    'name' => $expectedServerName,
                    'OS' => 'ubuntu-24.04',
                    'plan' => 'standard-base-g2',
                    'config' => [
                        'SSHKeys' => [],
                    ],
                ],
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::BASE_URL.'/vm/'.self::VM_ID,
        );
    }

    public function test_failed_details_poll_recovers_local_server_by_provider_identity_not_display_name(): void
    {
        $providerServerName = '';

        Http::fake(
            function (Request $request) use (&$providerServerName) {
                if ($request->url() === self::BASE_URL.'/plans') {
                    return Http::response($this->plansResponse());
                }

                if ($request->url() === self::BASE_URL.'/oss') {
                    return Http::response([
                        'ubuntu' => [
                            '24.04',
                            '22.04',
                        ],
                    ]);
                }

                if (
                    $request->method() === 'POST'
                    && $request->url() === self::BASE_URL.'/vm'
                ) {
                    return Http::response([
                        'taskID' => '6a80c1906727f3d124d794cb',
                        'VMID' => self::VM_ID,
                    ]);
                }

                if ($request->url() === self::BASE_URL.'/vm/'.self::VM_ID) {
                    return Http::response('', 500);
                }

                if (
                    $request->method() === 'GET'
                    && $request->url() === self::BASE_URL.'/vm'
                ) {
                    return Http::response([
                        'vms' => [
                            [
                                '_id' => self::VM_ID,
                                'plan' => 'standard-base-g2',
                                'OS' => 'ubuntu-24.04',
                                'state' => 'CREATED',
                                'name' => $providerServerName,
                                'guestCus' => [
                                    'status' => 'IDLE',
                                ],
                                'createdAt' => '2026-08-15T19:44:15.993Z',
                                'guestState' => 'NOT_RUNNING',
                                'power' => 'POWERED_OFF',
                            ],
                        ],
                    ]);
                }

                return Http::response('', 404);
            },
        );

        $user = User::factory()->create();

        $order = app(CreateOrderAction::class)->execute(
            user: $user,
            region: 'iran',
            sizeId: 'standard-base-g2',
            imageId: 'ubuntu-24.04',
            selectedDiskGiB: 20,
            period: '2_days',
            provider: CloudProviderType::Liara,
        );

        $providerServerName = $this->providerServerName(
            $order->getKey(),
        );

        $order->forceFill([
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ])->saveOrFail();

        $this->expectException(CloudConnectionException::class);

        try {
            app(ProvisionPaidOrderAction::class)->execute(
                $order->getKey(),
            );
        } finally {
            $freshOrder = $order->fresh();

            $this->assertSame(
                OrderStatus::Provisioning,
                $freshOrder->status,
            );
            $this->assertNotNull($freshOrder->server_id);

            $server = $freshOrder->server;

            $this->assertNotNull($server);
            $this->assertSame('liara', $server->cloud_provider);
            $this->assertSame(self::VM_ID, $server->cloud_server_id);
            $this->assertSame('iran', $server->cloud_region);
            $this->assertNotSame($providerServerName, $server->name);

            Http::assertSent(
                fn (Request $request): bool => $request->method() === 'GET'
                    && $request->url() === self::BASE_URL.'/vm',
            );
        }
    }

    private function providerServerName(int $orderId): string
    {
        return sprintf(
            'cf-%s',
            base_convert((string) $orderId, 10, 36),
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

    /**
     * @return array<string, mixed>
     */
    private function readyVmDetails(): array
    {
        return [
            '_id' => self::VM_ID,
            'plan' => 'standard-base-g2',
            'OS' => 'ubuntu-24.04',
            'state' => 'CREATED',
            'config' => [
                'SSHKeys' => [],
                'hostname' => 'ubuntu-coreflare',
                'rootPassword' => 'generated-root-password',
            ],
            'name' => 'cf-1',
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
}
