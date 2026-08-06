<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudServerReportsInterface;
use App\Domain\Cloud\Enums\CloudReportMetric;
use App\Domain\Cloud\Enums\CloudReportPeriod;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudProvider;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudProviderServerReportsTest extends TestCase
{
    private const string BASE_URL = 'https://napi.arvancloud.ir/ecc/v1';

    public function test_provider_implements_server_reports_contract(): void
    {
        $this->assertInstanceOf(
            CloudServerReportsInterface::class,
            $this->provider(),
        );
    }

    public function test_it_gets_server_reports_with_the_selected_period(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            self::BASE_URL
            .'/regions/eu-west1-a/reports/server-123?period=1h' => Http::response(
                self::validResponse(),
                200,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        ]);

        $result = $this->provider()->getServerReports(
            region: 'eu-west1-a',
            serverId: 'server-123',
            period: CloudReportPeriod::OneHour,
        );

        $this->assertSame(
            'eu-west1-a',
            $result->regionId,
        );

        $this->assertSame(
            'server-123',
            $result->serverId,
        );

        $this->assertSame(
            CloudReportPeriod::OneHour,
            $result->period,
        );

        $this->assertSame(
            [8],
            $result->cpu
                ->seriesFor(CloudReportMetric::CpuUsage)
                ?->values,
        );

        Http::assertSent(
            static fn (Request $request): bool => $request->method() === 'GET'
                && $request->url() === self::BASE_URL
                .'/regions/eu-west1-a/reports/server-123?period=1h',
        );

        Http::assertSentCount(1);
    }

    public function test_it_normalizes_the_region_and_server_identifier(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            self::BASE_URL
            .'/regions/eu-west1-a/reports/server-123?period=1d' => Http::response(
                self::validResponse(),
                200,
            ),
        ]);

        $result = $this->provider()->getServerReports(
            region: ' eu-west1-a ',
            serverId: ' server-123 ',
            period: CloudReportPeriod::OneDay,
        );

        $this->assertSame(
            'eu-west1-a',
            $result->regionId,
        );

        $this->assertSame(
            'server-123',
            $result->serverId,
        );

        Http::assertSent(
            static fn (Request $request): bool => $request->url()
                === self::BASE_URL
                .'/regions/eu-west1-a/reports/server-123?period=1d',
        );
    }

    #[DataProvider('invalidServerReferenceProvider')]
    public function test_it_rejects_invalid_server_references_before_sending_a_request(
        string $region,
        string $serverId,
    ): void {
        Http::preventStrayRequests();
        Http::fake();

        $this->expectException(
            CloudValidationException::class,
        );

        try {
            $this->provider()->getServerReports(
                region: $region,
                serverId: $serverId,
                period: CloudReportPeriod::OneHour,
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
            'server-123',
        ];

        yield 'whitespace region' => [
            '   ',
            'server-123',
        ];

        yield 'invalid region' => [
            'eu/west',
            'server-123',
        ];

        yield 'empty server identifier' => [
            'eu-west1-a',
            '',
        ];

        yield 'whitespace server identifier' => [
            'eu-west1-a',
            '   ',
        ];

        yield 'invalid server identifier' => [
            'eu-west1-a',
            'server/123',
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
                'charts' => [
                    'cpu' => [
                        'title' => 'reports.cpu',
                        'categories' => [
                            '2026-08-06T15:00:00Z',
                        ],
                        'series' => [
                            [
                                'name' => 'iaas.reports.cpu',
                                'data' => [8],
                            ],
                        ],
                    ],
                    'ram' => [
                        'title' => 'reports.ram',
                        'categories' => [
                            '2026-08-06T15:00:00Z',
                        ],
                        'series' => [
                            [
                                'name' => 'iaas.reports.ram',
                                'data' => [183312384],
                            ],
                        ],
                    ],
                    'network' => [
                        'title' => 'reports.network',
                        'categories' => [
                            '2026-08-06T15:00:00Z',
                        ],
                        'series' => [
                            [
                                'name' => 'iaas.reports.networkincoming',
                                'data' => [4188.44],
                            ],
                            [
                                'name' => 'iaas.reports.networkoutgoing',
                                'data' => [222.84333333333333],
                            ],
                        ],
                    ],
                    'disk' => [
                        'title' => 'reports.disk',
                        'categories' => [
                            '2026-08-06T15:00:00Z',
                        ],
                        'series' => [
                            [
                                'name' => 'iaas.reports.diskread',
                                'data' => [434109.44],
                            ],
                            [
                                'name' => 'iaas.reports.diskwrite',
                                'data' => [4982947.84],
                            ],
                        ],
                    ],
                    'statistics' => [],
                ],
            ],
        ];
    }
}
