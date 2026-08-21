<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudQuotaData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_lists_regions(): void
    {
        Http::fake([
            '*' => Http::response(
                $this->fixture('regions.json'),
            ),
        ]);

        $regions = $this->provider()->listRegions();

        $this->assertNotEmpty($regions);

        $this->assertInstanceOf(
            CloudRegionData::class,
            $regions[0],
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions',
        );

        Http::assertSentCount(1);
    }

    public function test_it_lists_region_sizes(): void
    {
        Http::fake([
            '*' => Http::response(
                $this->fixture('sizes.json'),
            ),
        ]);

        $sizes = $this->provider()->listSizes(
            'eu-west1-a',
        );

        $this->assertNotEmpty($sizes);

        $this->assertInstanceOf(
            CloudSizeData::class,
            $sizes[0],
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/sizes',
        );

        Http::assertSentCount(1);
    }

    public function test_it_lists_distribution_images(): void
    {
        Http::fake([
            '*' => Http::response(
                $this->fixture('images.json'),
            ),
        ]);

        $images = $this->provider()->listImages(
            'eu-west1-a',
        );

        $this->assertNotEmpty($images);

        $this->assertInstanceOf(
            CloudImageData::class,
            $images[0],
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/images?type=distributions',
        );

        Http::assertSentCount(1);
    }

    public function test_it_reads_the_purchase_catalog_through_the_interactive_transport(): void
    {
        Http::fake([
            '*/regions' => Http::response(
                $this->fixture('regions.json'),
            ),
            '*/sizes' => Http::response(
                $this->fixture('sizes.json'),
            ),
            '*/images*' => Http::response(
                $this->fixture('images.json'),
            ),
        ]);

        $provider = $this->provider();

        $this->assertNotEmpty(
            $provider->listPurchaseRegions(),
        );
        $this->assertNotEmpty(
            $provider->listPurchaseSizes(
                'eu-west1-a',
            ),
        );
        $this->assertNotEmpty(
            $provider->listPurchaseImages(
                'eu-west1-a',
            ),
        );

        Http::assertSentCount(3);
    }

    public function test_it_lists_networks(): void
    {
        Http::fake([
            '*' => Http::response(
                $this->fixture('networks.json'),
            ),
        ]);

        $networks = $this->provider()->listNetworks(
            'eu-west1-a',
        );

        $this->assertNotEmpty($networks);

        $this->assertInstanceOf(
            CloudNetworkData::class,
            $networks[0],
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/networks',
        );

        Http::assertSentCount(1);
    }

    public function test_it_lists_security_groups_using_securities_endpoint(): void
    {
        Http::fake([
            '*' => Http::response(
                $this->fixture(
                    'security-groups.json',
                ),
            ),
        ]);

        $groups = $this->provider()->listSecurityGroups(
            'eu-west1-a',
        );

        $this->assertNotEmpty($groups);

        $this->assertInstanceOf(
            CloudSecurityGroupData::class,
            $groups[0],
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/securities',
        );

        Http::assertSentCount(1);
    }

    public function test_it_gets_region_quota(): void
    {
        Http::fake([
            '*' => Http::response(
                $this->fixture('quota.json'),
            ),
        ]);

        $quota = $this->provider()->getQuota(
            'eu-west1-a',
        );

        $this->assertInstanceOf(
            CloudQuotaData::class,
            $quota,
        );

        $this->assertSame(
            'eu-west1-a',
            $quota->regionId,
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/quota',
        );

        Http::assertSentCount(1);
    }

    public function test_it_requests_ssh_keys_without_trailing_slash(): void
    {
        Http::fake([
            '*' => Http::response([
                'data' => [],
            ]),
        ]);

        try {
            $this->provider()->listSshKeys(
                'eu-west1-a',
            );

            $this->fail(
                'Expected SSH key mapping to remain blocked.',
            );
        } catch (CloudUnexpectedResponseException $exception) {
            $this->assertSame(
                'ArvanCloud SSH key response schema has not been verified.',
                $exception->getMessage(),
            );
        }

        Http::assertSent(
            fn (Request $request): bool => $request->url() ===
                'https://api.example.test/ecc/v1/regions/eu-west1-a/ssh-keys',
        );

        Http::assertSentCount(1);
    }

    #[DataProvider('invalidRegionProvider')]
    public function test_it_rejects_invalid_regions(
        string $region,
    ): void {
        Http::fake();

        try {
            $this->provider()->listSizes($region);

            $this->fail(
                'Expected invalid cloud region to be rejected.',
            );
        } catch (CloudValidationException) {
            $this->assertTrue(true);
        }

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRegionProvider(): array
    {
        return [
            'empty' => [
                '',
            ],
            'whitespace' => [
                '   ',
            ],
            'path traversal' => [
                '../eu-west1-a',
            ],
            'slash' => [
                'eu/west1-a',
            ],
            'query string' => [
                'eu-west1-a?type=test',
            ],
            'fragment' => [
                'eu-west1-a#test',
            ],
            'absolute URL' => [
                'https://example.com',
            ],
            'control character' => [
                "eu-west1-a\n",
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

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function fixture(string $name): array
    {
        $path = base_path(
            "tests/Fixtures/Cloud/ArvanCloud/{$name}",
        );

        $contents = file_get_contents($path);

        $this->assertNotFalse(
            $contents,
            "Unable to read fixture [{$name}].",
        );

        $payload = json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray($payload);

        return $payload;
    }
}
