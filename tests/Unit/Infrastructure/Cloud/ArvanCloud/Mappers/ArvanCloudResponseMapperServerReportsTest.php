<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\Enums\CloudReportMetric;
use App\Domain\Cloud\Enums\CloudReportPeriod;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ArvanCloudResponseMapperServerReportsTest extends TestCase
{
    public function test_it_maps_the_verified_server_reports_response(): void
    {
        $result = (new ArvanCloudResponseMapper)->mapServerReports(
            payload: self::validPayload(),
            regionId: 'eu-west1-a',
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

        $this->assertTrue(
            $result->hasData(),
        );

        $this->assertSame(
            '2026-08-06T15:00:00+00:00',
            $result->cpu->timestamps[0]->format(
                DATE_ATOM,
            ),
        );

        $this->assertSame(
            [
                8,
            ],
            $result->cpu
                ->seriesFor(
                    CloudReportMetric::CpuUsage,
                )
                ?->values,
        );

        $this->assertSame(
            [
                144539648,
                183312384,
            ],
            $result->ram
                ->seriesFor(
                    CloudReportMetric::RamUsage,
                )
                ?->values,
        );

        $this->assertSame(
            [
                4188.44,
            ],
            $result->network
                ->seriesFor(
                    CloudReportMetric::NetworkIncoming,
                )
                ?->values,
        );

        $this->assertSame(
            [
                222.84333333333333,
            ],
            $result->network
                ->seriesFor(
                    CloudReportMetric::NetworkOutgoing,
                )
                ?->values,
        );

        $this->assertSame(
            [
                434109.44,
            ],
            $result->disk
                ->seriesFor(
                    CloudReportMetric::DiskRead,
                )
                ?->values,
        );

        $this->assertSame(
            [
                4982947.84,
            ],
            $result->disk
                ->seriesFor(
                    CloudReportMetric::DiskWrite,
                )
                ?->values,
        );
    }

    public function test_it_maps_metrics_by_name_instead_of_series_position(): void
    {
        $payload = self::validPayload();

        $payload['data']['charts']['network']['series'] = array_reverse(
            $payload['data']['charts']['network']['series'],
        );

        $result = (new ArvanCloudResponseMapper)->mapServerReports(
            payload: $payload,
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            period: CloudReportPeriod::OneMinute,
        );

        $this->assertSame(
            [
                4188.44,
            ],
            $result->network
                ->seriesFor(
                    CloudReportMetric::NetworkIncoming,
                )
                ?->values,
        );

        $this->assertSame(
            [
                222.84333333333333,
            ],
            $result->network
                ->seriesFor(
                    CloudReportMetric::NetworkOutgoing,
                )
                ?->values,
        );
    }

    public function test_it_maps_an_empty_report_without_creating_fake_points(): void
    {
        $payload = self::validPayload();

        foreach ([
            'cpu',
            'ram',
            'network',
            'disk',
        ] as $chart) {
            $payload['data']['charts'][$chart]['categories'] = [];
            $payload['data']['charts'][$chart]['series'] = [];
        }

        $result = (new ArvanCloudResponseMapper)->mapServerReports(
            payload: $payload,
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            period: CloudReportPeriod::OneDay,
        );

        $this->assertFalse(
            $result->hasData(),
        );

        $this->assertTrue(
            $result->cpu->isEmpty(),
        );
    }

    public function test_it_normalizes_a_transient_null_series_as_an_empty_chart(): void
    {
        $payload = self::validPayload();

        $payload['data']['charts']['cpu']['categories'] = [
            '2026-08-06T16:22:42Z',
        ];

        $payload['data']['charts']['cpu']['series'] = null;

        $result = (new ArvanCloudResponseMapper)->mapServerReports(
            payload: $payload,
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            period: CloudReportPeriod::OneHour,
        );

        $this->assertTrue(
            $result->cpu->isEmpty(),
        );

        $this->assertSame(
            [],
            $result->cpu->timestamps,
        );

        $this->assertSame(
            [],
            $result->cpu->series,
        );

        $this->assertTrue(
            $result->hasData(),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    #[DataProvider('invalidPayloadProvider')]
    public function test_it_rejects_invalid_server_report_payloads(
        array $payload,
    ): void {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        (new ArvanCloudResponseMapper)->mapServerReports(
            payload: $payload,
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            period: CloudReportPeriod::OneHour,
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'missing data envelope' => [
            [],
        ];

        $payload = self::validPayload();
        unset($payload['data']['charts']);

        yield 'missing charts object' => [
            $payload,
        ];

        $payload = self::validPayload();
        $payload['data']['charts']['cpu']['categories'] = [
            'not-a-timestamp',
        ];

        yield 'invalid timestamp' => [
            $payload,
        ];

        $payload = self::validPayload();
        $payload['data']['charts']['cpu']['series'][0]['name'] =
            'iaas.reports.unknown';

        yield 'unknown metric' => [
            $payload,
        ];

        $payload = self::validPayload();
        $payload['data']['charts']['network']['series'][] =
            $payload['data']['charts']['network']['series'][0];

        yield 'duplicate metric' => [
            $payload,
        ];

        $payload = self::validPayload();
        $payload['data']['charts']['cpu']['series'][0]['data'] = [];

        yield 'point count mismatch' => [
            $payload,
        ];

        $payload = self::validPayload();
        $payload['data']['charts']['cpu']['series'][0]['data'] = [
            -1,
        ];

        yield 'negative metric value' => [
            $payload,
        ];

        $payload = self::validPayload();
        $payload['data']['charts']['network']['series'] = [
            $payload['data']['charts']['network']['series'][1],
        ];

        yield 'missing expected metric' => [
            $payload,
        ];

        $payload = self::validPayload();
        $payload['data']['charts']['statistics'] = [
            [
                'unknown' => true,
            ],
        ];

        yield 'unverified statistics schema' => [
            $payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validPayload(): array
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
                                'data' => [
                                    8,
                                ],
                            ],
                        ],
                    ],

                    'ram' => [
                        'title' => 'reports.ram',
                        'categories' => [
                            '2026-08-06T14:55:00Z',
                            '2026-08-06T15:00:00Z',
                        ],
                        'series' => [
                            [
                                'name' => 'iaas.reports.ram',
                                'data' => [
                                    144539648,
                                    183312384,
                                ],
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
                                'name' => 'iaas.reports.networkoutgoing',
                                'data' => [
                                    222.84333333333333,
                                ],
                            ],
                            [
                                'name' => 'iaas.reports.networkincoming',
                                'data' => [
                                    4188.44,
                                ],
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
                                'data' => [
                                    434109.44,
                                ],
                            ],
                            [
                                'name' => 'iaas.reports.diskwrite',
                                'data' => [
                                    4982947.84,
                                ],
                            ],
                        ],
                    ],

                    'statistics' => [],
                ],
            ],
        ];
    }
}
