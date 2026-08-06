<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\DTOs\ResizeCloudRootDiskData;
use App\Domain\Cloud\DTOs\ResizeCloudServerData;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudProviderResizeTest extends TestCase
{
    private const string BASE_URL =
        'https://api.example.test/ecc/v1';

    private const string REGION_ID =
        'eu-west1-a';

    private const string SERVER_ID =
        'ff83466c-c0fe-4dc4-9d1d-bde29efd0b45';

    private const string TARGET_SIZE_ID =
        'eco-4-8-0';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_submits_server_resize_request(): void
    {
        Http::fake([
            $this->serverResizeUrl() => Http::response(
                '',
                202,
            ),
        ]);

        $this->provider()->resizeServer(
            new ResizeCloudServerData(
                regionId: self::REGION_ID,
                serverId: self::SERVER_ID,
                targetSizeId: self::TARGET_SIZE_ID,
                targetDiskGiB: 100,
            ),
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'POST'
                    && $request->url() ===
                    $this->serverResizeUrl()
                    && $request->data() === [
                        'disk_size' => 100,
                        'flavor_id' => self::TARGET_SIZE_ID,
                    ];
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_submits_root_disk_resize_request(): void
    {
        Http::fake([
            $this->rootDiskResizeUrl() => Http::response(
                '',
                202,
            ),
        ]);

        $this->provider()->resizeRootDisk(
            new ResizeCloudRootDiskData(
                regionId: self::REGION_ID,
                serverId: self::SERVER_ID,
                targetDiskGiB: 150,
            ),
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'PUT'
                    && $request->url() ===
                    $this->rootDiskResizeUrl()
                    && $request->data() === [
                        'new_size' => 150,
                    ];
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_normalizes_resize_identifiers_before_request(): void
    {
        Http::fake([
            $this->serverResizeUrl() => Http::response(
                '',
                202,
            ),
        ]);

        $this->provider()->resizeServer(
            new ResizeCloudServerData(
                regionId: '  '.self::REGION_ID.'  ',
                serverId: '  '.self::SERVER_ID.'  ',
                targetSizeId: '  '.self::TARGET_SIZE_ID.'  ',
                targetDiskGiB: 100,
            ),
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->method() === 'POST'
                    && $request->url() ===
                    $this->serverResizeUrl()
                    && $request->data() === [
                        'disk_size' => 100,
                        'flavor_id' => self::TARGET_SIZE_ID,
                    ];
            },
        );

        Http::assertSentCount(1);
    }

    public function test_it_normalizes_root_disk_resize_identifiers_before_request(): void
    {
        Http::fake([
            $this->rootDiskResizeUrl() => Http::response(
                '',
                202,
            ),
        ]);

        $this->provider()->resizeRootDisk(
            new ResizeCloudRootDiskData(
                regionId: '  '.self::REGION_ID.'  ',
                serverId: '  '.self::SERVER_ID.'  ',
                targetDiskGiB: 150,
            ),
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'PUT'
                && $request->url() ===
                $this->rootDiskResizeUrl()
                && $request->data() === [
                    'new_size' => 150,
                ],
        );

        Http::assertSentCount(1);
    }

    public function test_it_rejects_invalid_resize_region_before_request(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->resizeServer(
                new ResizeCloudServerData(
                    regionId: '../invalid-region',
                    serverId: self::SERVER_ID,
                    targetSizeId: self::TARGET_SIZE_ID,
                    targetDiskGiB: 100,
                ),
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_invalid_resize_server_identifier_before_request(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->resizeServer(
                new ResizeCloudServerData(
                    regionId: self::REGION_ID,
                    serverId: '../invalid-server',
                    targetSizeId: self::TARGET_SIZE_ID,
                    targetDiskGiB: 100,
                ),
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_invalid_target_size_identifier_before_request(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->resizeServer(
                new ResizeCloudServerData(
                    regionId: self::REGION_ID,
                    serverId: self::SERVER_ID,
                    targetSizeId: '../invalid-size',
                    targetDiskGiB: 100,
                ),
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_invalid_root_disk_server_identifier_before_request(): void
    {
        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->resizeRootDisk(
                new ResizeCloudRootDiskData(
                    regionId: self::REGION_ID,
                    serverId: '../invalid-server',
                    targetDiskGiB: 150,
                ),
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    #[DataProvider('invalidDiskSizeProvider')]
    public function test_it_rejects_invalid_server_resize_disk_size(
        int $diskGiB,
    ): void {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server disk size must be greater than zero.',
        );

        try {
            $this->provider()->resizeServer(
                new ResizeCloudServerData(
                    regionId: self::REGION_ID,
                    serverId: self::SERVER_ID,
                    targetSizeId: self::TARGET_SIZE_ID,
                    targetDiskGiB: $diskGiB,
                ),
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    #[DataProvider('invalidDiskSizeProvider')]
    public function test_it_rejects_invalid_root_disk_resize_size(
        int $diskGiB,
    ): void {
        $this->expectException(
            CloudValidationException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server disk size must be greater than zero.',
        );

        try {
            $this->provider()->resizeRootDisk(
                new ResizeCloudRootDiskData(
                    regionId: self::REGION_ID,
                    serverId: self::SERVER_ID,
                    targetDiskGiB: $diskGiB,
                ),
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return array<string, array{diskGiB: int}>
     */
    public static function invalidDiskSizeProvider(): array
    {
        return [
            'zero' => [
                'diskGiB' => 0,
            ],

            'negative' => [
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

    private function serverResizeUrl(): string
    {
        return sprintf(
            '%s/regions/%s/servers/%s/resize',
            self::BASE_URL,
            self::REGION_ID,
            self::SERVER_ID,
        );
    }

    private function rootDiskResizeUrl(): string
    {
        return sprintf(
            '%s/regions/%s/servers/%s/resizeRoot',
            self::BASE_URL,
            self::REGION_ID,
            self::SERVER_ID,
        );
    }
}
