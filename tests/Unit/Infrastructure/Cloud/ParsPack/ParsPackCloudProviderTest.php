<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ParsPack;

use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Server\Enums\AuthenticationType;
use App\Infrastructure\Cloud\ParsPack\Mappers\ParsPackCloudResponseMapper;
use App\Infrastructure\Cloud\ParsPack\ParsPackCloudClient;
use App\Infrastructure\Cloud\ParsPack\ParsPackCloudProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ParsPackCloudProviderTest extends TestCase
{
    private const string BASE_URL = 'https://my.parspack.example.test/cserver/api/public/v1';

    private const string PRIVATE_KEY = "-----BEGIN OPENSSH PRIVATE KEY-----\ntest-bootstrap-private-key\n-----END OPENSSH PRIVATE KEY-----";

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_create_uses_fixed_size_and_configured_bootstrap_key(): void
    {
        Http::fake([
            self::BASE_URL.'/sizes*' => Http::response($this->sizesResponse()),
            self::BASE_URL.'/vms' => Http::response([
                'vm' => [
                    'id' => 'a34a-4c03-5f73-1a14',
                    'name' => 'coreflare-api-test',
                    'status' => 'new',
                    'created_at' => '2026-08-28T08:26:51.000000Z',
                    'region' => [
                        'name' => 'frankfurt',
                        'slug' => 'frankfurt',
                    ],
                ],
            ], 201),
        ]);

        $created = $this->provider()->createServer(
            $this->createData(),
        );

        $this->assertSame('a34a-4c03-5f73-1a14', $created->id);
        $this->assertNull($created->generatedPassword());

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === self::BASE_URL.'/vms'
                && $request->data() === [
                    'name' => 'coreflare-api-test',
                    'region' => 'frankfurt',
                    'size' => 'deVPS2',
                    'image' => 'ubuntu24-cloudinit-qcow2',
                    'ssh_keys' => [14956],
                    'backups' => false,
                    'ipv6' => false,
                ],
        );
    }

    public function test_bootstrap_credential_is_the_configured_private_key(): void
    {
        $provider = $this->provider();
        $credential = $provider->bootstrapCredential(
            request: $this->createData(),
            server: new \App\Domain\Cloud\DTOs\CreatedCloudServerData(
                id: 'a34a-4c03-5f73-1a14',
                name: 'coreflare-api-test',
                regionId: 'frankfurt',
                status: \App\Domain\Cloud\Enums\CloudServerStatus::Provisioning,
                username: 'root',
                createdAt: null,
                generatedPassword: null,
            ),
        );

        $this->assertSame(AuthenticationType::SSHKey, $credential->authenticationType);
        $this->assertSame(self::PRIVATE_KEY, $credential->credential());
    }

    public function test_power_actions_use_parspack_action_endpoint(): void
    {
        Http::fake([
            self::BASE_URL.'/vms/*/actions' => Http::response([
                'action' => [
                    'id' => 5652602,
                    'status' => 'in-progress',
                    'type' => 'command',
                ],
            ], 201),
        ]);

        $provider = $this->provider();
        $provider->powerOff('frankfurt', 'a34a-4c03-5f73-1a14');
        $provider->powerOn('frankfurt', 'a34a-4c03-5f73-1a14');
        $provider->reboot('frankfurt', 'a34a-4c03-5f73-1a14');

        $payloads = [];
        Http::assertSent(function (Request $request) use (&$payloads): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            $payloads[] = $request->data();

            return true;
        });

        $this->assertContains(['type' => 'power_off'], $payloads);
        $this->assertContains(['type' => 'power_on'], $payloads);
        $this->assertContains(['type' => 'reboot'], $payloads);
    }

    public function test_delete_uses_direct_vm_delete_endpoint(): void
    {
        Http::fake([
            self::BASE_URL.'/vms/*' => Http::response('', 204),
        ]);

        $this->provider()->deleteServer(
            'frankfurt',
            'a34a-4c03-5f73-1a14',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'DELETE'
                && $request->url() === self::BASE_URL.'/vms/a34a-4c03-5f73-1a14',
        );
    }

    public function test_purchase_catalog_defaults_to_normalized_price_without_funding_overhead(): void
    {
        Http::fake([
            self::BASE_URL.'/sizes*' => Http::response($this->sizesResponse()),
        ]);

        $sizes = $this->provider()->listPurchaseSizes('frankfurt');

        $this->assertCount(1, $sizes);
        $this->assertSame('17312.5', $sizes[0]->hourlyPrice?->amount);
        $this->assertSame('12469200', $sizes[0]->monthlyPrice?->amount);
        $this->assertSame('IRR', $sizes[0]->hourlyPrice?->currencyCode);
    }

    public function test_purchase_catalog_applies_configured_funding_overhead(): void
    {
        Http::fake([
            self::BASE_URL.'/sizes*' => Http::response($this->sizesResponse()),
        ]);

        $sizes = $this->provider(fundingOverheadPercent: 10)
            ->listPurchaseSizes('frankfurt');

        $this->assertCount(1, $sizes);
        $this->assertSame('19044', $sizes[0]->hourlyPrice?->amount);
        $this->assertSame('13716120', $sizes[0]->monthlyPrice?->amount);
    }

    public function test_operational_catalog_keeps_normalized_provider_price(): void
    {
        Http::fake([
            self::BASE_URL.'/sizes*' => Http::response($this->sizesResponse()),
        ]);

        $sizes = $this->provider(fundingOverheadPercent: 10)
            ->listSizes('frankfurt');

        $this->assertSame('17312.5', $sizes[0]->hourlyPrice?->amount);
        $this->assertSame('12469200', $sizes[0]->monthlyPrice?->amount);
        $this->assertSame('IRR', $sizes[0]->hourlyPrice?->currencyCode);
    }

    public function test_fixed_disk_price_is_zero_for_included_disk(): void
    {
        Http::fake([
            self::BASE_URL.'/sizes*' => Http::response($this->sizesResponse()),
        ]);

        $price = $this->provider()->calculatePurchaseDiskPrice(
            region: 'frankfurt',
            sizeId: 'deVPS2',
            diskGiB: 40,
        );

        $this->assertSame('0', $price->hourlyPrice->amount);
        $this->assertSame('0', $price->monthlyPrice->amount);
    }

    private function provider(
        int $fundingOverheadPercent = 0,
    ): ParsPackCloudProvider {
        return new ParsPackCloudProvider(
            client: new ParsPackCloudClient(
                baseUrl: self::BASE_URL,
                apiToken: 'test-token',
                retryMaxAttempts: 1,
            ),
            mapper: new ParsPackCloudResponseMapper(),
            bootstrapSshKeyId: 14956,
            bootstrapPrivateKey: self::PRIVATE_KEY,
            fundingOverheadPercent: $fundingOverheadPercent,
        );
    }

    private function createData(): CreateCloudServerData
    {
        return new CreateCloudServerData(
            name: 'coreflare-api-test',
            regionId: 'frankfurt',
            sizeId: 'deVPS2',
            imageId: 'ubuntu24-cloudinit-qcow2',
            diskGiB: 40,
        );
    }

    /** @return array<string, mixed> */
    private function sizesResponse(): array
    {
        return [
            'sizes' => [
                [
                    'slug' => 'deVPS2',
                    'memory' => 2048,
                    'vcpus' => 1,
                    'disk' => 40,
                    'transfer' => 1,
                    'transfer_type' => 'total_traffic',
                    'price_monthly' => 1246920,
                    'price_hourly' => 1731.25,
                    'regions' => ['frankfurt'],
                    'description' => '',
                    'id' => 997,
                    'available' => true,
                ],
            ],
            'links' => [],
            'meta' => ['total' => 1],
        ];
    }
}
