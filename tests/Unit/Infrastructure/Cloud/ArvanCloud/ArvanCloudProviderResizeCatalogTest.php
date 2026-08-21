<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudProviderResizeCatalogTest extends TestCase
{
    private const string BASE_URL =
        'https://api.example.test/ecc/v1';

    private const string REGION_ID =
        'eu-west1-a';

    private const string SERVER_ID =
        'ff83466c-c0fe-4dc4-9d1d-bde29efd0b45';

    private const string SIZE_ID =
        'eco-2-2-0';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_lists_available_resize_plans_for_server(): void
    {
        Http::fake([
            $this->serverResizePlansUrl() => Http::response([
                'data' => [
                    $this->resizePlanObject(),

                    $this->resizePlanObject([
                        'id' => 'eco-4-8-0',
                        'name' => 'eco-large',
                        'cpu_count' => 4,
                        'memory_in_bytes' => 8_589_934_592,
                        'disk_in_bytes' => 107_374_182_400,
                        'price_per_hour' => 46_400,
                        'price_per_month' => 33_408_000,
                    ]),
                ],
            ]),
        ]);

        $plans = $this->provider()->listServerResizePlans(
            region: self::REGION_ID,
            serverId: self::SERVER_ID,
        );

        $this->assertCount(
            2,
            $plans,
        );

        $plan = $plans[0];

        $this->assertInstanceOf(
            CloudSizeData::class,
            $plan,
        );

        $this->assertSame(
            self::SIZE_ID,
            $plan->id,
        );

        $this->assertSame(
            'eco-small4',
            $plan->name,
        );

        $this->assertSame(
            self::REGION_ID,
            $plan->regionId,
        );

        $this->assertSame(
            2,
            $plan->vCpu,
        );

        $this->assertSame(
            2048,
            $plan->memoryMiB,
        );

        $this->assertSame(
            50,
            $plan->diskGiB,
        );

        $this->assertSame(
            'economic',
            $plan->category,
        );

        $this->assertSame(
            '23200.5',
            $plan->hourlyPrice?->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Hourly,
            $plan->hourlyPrice?->billingPeriod,
        );

        $this->assertSame(
            '16704000',
            $plan->monthlyPrice?->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Monthly,
            $plan->monthlyPrice?->billingPeriod,
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() ===
                $this->serverResizePlansUrl(),
        );

        Http::assertSentCount(1);
    }

    public function test_it_finds_size_details(): void
    {
        Http::fake([
            $this->sizeUrl() => Http::response([
                'data' => $this->sizeObject(),
            ]),
        ]);

        $size = $this->provider()->findSize(
            region: self::REGION_ID,
            sizeId: self::SIZE_ID,
        );

        $this->assertSize(
            size: $size,
            diskGiB: 50,
            hourlyPrice: '23200',
            monthlyPrice: '16704000',
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === $this->sizeUrl(),
        );

        Http::assertSentCount(1);
    }

    public function test_it_calculates_size_with_custom_disk_capacity(): void
    {
        Http::fake([
            $this->sizeUrl() => Http::response([
                'data' => $this->sizeObject([
                    'disk' => 80,
                    'price_per_hour' => 25_750.75,
                    'price_per_month' => '18540540.50',
                ]),
            ]),
        ]);

        $size = $this->provider()->calculateSize(
            region: self::REGION_ID,
            sizeId: self::SIZE_ID,
            diskGiB: 80,
        );

        $this->assertSize(
            size: $size,
            diskGiB: 80,
            hourlyPrice: '25750.75',
            monthlyPrice: '18540540.50',
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'POST'
                    && $request->url() === $this->sizeUrl()
                    && $request->data() === [
                        'volume_size' => 80,
                    ];
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_calculates_disk_price(): void
    {
        Http::fake([
            $this->sizeDiskUrl() => Http::response([
                'data' => [
                    'disk' => 80,
                    'price_per_hour' => 2550.25,
                    'price_per_month' => '1836180.50',
                ],
            ]),
        ]);

        $price = $this->provider()->calculateDiskPrice(
            region: self::REGION_ID,
            sizeId: self::SIZE_ID,
            diskGiB: 80,
        );

        $this->assertInstanceOf(
            CloudDiskPriceData::class,
            $price,
        );

        $this->assertSame(
            80,
            $price->diskGiB,
        );

        $this->assertSame(
            '2550.25',
            $price->hourlyPrice->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Hourly,
            $price->hourlyPrice->billingPeriod,
        );

        $this->assertNull(
            $price->hourlyPrice->currencyCode,
        );

        $this->assertSame(
            '1836180.50',
            $price->monthlyPrice->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Monthly,
            $price->monthlyPrice->billingPeriod,
        );

        $this->assertNull(
            $price->monthlyPrice->currencyCode,
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'POST'
                    && $request->url() ===
                    $this->sizeDiskUrl()
                    && $request->data() === [
                        'volume_size' => 80,
                    ];
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_calculates_purchase_disk_price_through_the_interactive_transport(): void
    {
        Http::fake([
            $this->sizeDiskUrl() => Http::response([
                'data' => [
                    'disk' => 80,
                    'price_per_hour' => 2550.25,
                    'price_per_month' => '1836180.50',
                ],
            ]),
        ]);

        $price = $this->provider()->calculatePurchaseDiskPrice(
            region: self::REGION_ID,
            sizeId: self::SIZE_ID,
            diskGiB: 80,
        );

        $this->assertSame(
            80,
            $price->diskGiB,
        );
        $this->assertSame(
            '2550.25',
            $price->hourlyPrice->amount,
        );
        $this->assertSame(
            '1836180.50',
            $price->monthlyPrice->amount,
        );

        Http::assertSentCount(1);
    }

    public function test_it_normalizes_region_and_resource_identifiers(): void
    {
        Http::fake([
            $this->sizeUrl() => Http::response([
                'data' => $this->sizeObject(),
            ]),
        ]);

        $size = $this->provider()->findSize(
            region: '  '.self::REGION_ID.'  ',
            sizeId: '  '.self::SIZE_ID.'  ',
        );

        $this->assertSame(
            self::REGION_ID,
            $size->regionId,
        );

        $this->assertSame(
            self::SIZE_ID,
            $size->id,
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url() === $this->sizeUrl(),
        );

        Http::assertSentCount(1);
    }

    public function test_it_rejects_invalid_server_identifier_before_request(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->listServerResizePlans(
                region: self::REGION_ID,
                serverId: '../invalid-server-id',
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_invalid_size_identifier_before_request(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->findSize(
                region: self::REGION_ID,
                sizeId: '../invalid-size-id',
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_invalid_region_before_request(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->findSize(
                region: '../invalid-region',
                sizeId: self::SIZE_ID,
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    #[DataProvider('invalidDiskSizeProvider')]
    public function test_it_rejects_invalid_disk_size_before_request(
        string $method,
        int $diskGiB,
    ): void {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server disk size must be greater than zero.',
        );

        try {
            $this->provider()->{$method}(
                region: self::REGION_ID,
                sizeId: self::SIZE_ID,
                diskGiB: $diskGiB,
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return array<string, array{
     *     method: string,
     *     diskGiB: int
     * }>
     */
    public static function invalidDiskSizeProvider(): array
    {
        return [
            'calculate size with zero disk' => [
                'method' => 'calculateSize',
                'diskGiB' => 0,
            ],

            'calculate size with negative disk' => [
                'method' => 'calculateSize',
                'diskGiB' => -1,
            ],

            'calculate disk price with zero disk' => [
                'method' => 'calculateDiskPrice',
                'diskGiB' => 0,
            ],

            'calculate disk price with negative disk' => [
                'method' => 'calculateDiskPrice',
                'diskGiB' => -1,
            ],
        ];
    }

    private function provider(): ArvanCloudProvider
    {
        return new ArvanCloudProvider(
            client: new ArvanCloudClient(
                baseUrl: self::BASE_URL,
                apiKey: 'test-api-key',
                connectTimeout: 5,
                requestTimeout: 15,
            ),

            mapper: new ArvanCloudResponseMapper,

            createType: 'cinder',

            defaultUsername: 'ubuntu',
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function resizePlanObject(
        array $overrides = [],
    ): array {
        return array_replace(
            [
                'availabilityZone' => self::REGION_ID,
                'id' => self::SIZE_ID,
                'name' => 'eco-small4',
                'cpu_count' => 2,
                'memory_in_bytes' => 2_147_483_648,
                'disk_in_bytes' => 53_687_091_200,
                'type' => 'economic',
                'price_per_hour' => 23_200.5,
                'price_per_month' => 16_704_000,
            ],
            $overrides,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sizeObject(
        array $overrides = [],
    ): array {
        return array_replace(
            [
                'id' => self::SIZE_ID,
                'name' => 'eco-small4',
                'cpu_count' => 2,
                'memory' => 2048,
                'disk' => 50,
                'type' => 'economic',
                'price_per_hour' => 23_200,
                'price_per_month' => 16_704_000,
            ],
            $overrides,
        );
    }

    private function assertSize(
        CloudSizeData $size,
        int $diskGiB,
        string $hourlyPrice,
        string $monthlyPrice,
    ): void {
        $this->assertSame(
            self::SIZE_ID,
            $size->id,
        );

        $this->assertSame(
            'eco-small4',
            $size->name,
        );

        $this->assertSame(
            self::REGION_ID,
            $size->regionId,
        );

        $this->assertSame(
            2,
            $size->vCpu,
        );

        $this->assertSame(
            2048,
            $size->memoryMiB,
        );

        $this->assertSame(
            $diskGiB,
            $size->diskGiB,
        );

        $this->assertSame(
            'economic',
            $size->category,
        );

        $this->assertSame(
            $hourlyPrice,
            $size->hourlyPrice?->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Hourly,
            $size->hourlyPrice?->billingPeriod,
        );

        $this->assertSame(
            $monthlyPrice,
            $size->monthlyPrice?->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Monthly,
            $size->monthlyPrice?->billingPeriod,
        );
    }

    private function serverResizePlansUrl(): string
    {
        return sprintf(
            '%s/regions/%s/sizes/by-server/%s',
            self::BASE_URL,
            self::REGION_ID,
            self::SERVER_ID,
        );
    }

    private function sizeUrl(): string
    {
        return sprintf(
            '%s/regions/%s/sizes/%s',
            self::BASE_URL,
            self::REGION_ID,
            self::SIZE_ID,
        );
    }

    private function sizeDiskUrl(): string
    {
        return sprintf(
            '%s/disk',
            $this->sizeUrl(),
        );
    }
}
