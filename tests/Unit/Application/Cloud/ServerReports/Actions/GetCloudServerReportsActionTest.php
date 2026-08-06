<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerReports\Actions;

use App\Application\Cloud\ServerReports\Actions\GetCloudServerReportsAction;
use App\Domain\Cloud\Contracts\CloudServerReportsInterface;
use App\Domain\Cloud\DTOs\CloudReportChartData;
use App\Domain\Cloud\DTOs\CloudServerReportsData;
use App\Domain\Cloud\Enums\CloudReportPeriod;
use Tests\TestCase;

final class GetCloudServerReportsActionTest extends TestCase
{
    public function test_it_returns_server_reports_from_the_provider(): void
    {
        $expected = new CloudServerReportsData(
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            period: CloudReportPeriod::OneHour,
            cpu: self::emptyChart(),
            ram: self::emptyChart(),
            network: self::emptyChart(),
            disk: self::emptyChart(),
        );

        $provider = $this->createMock(
            CloudServerReportsInterface::class,
        );

        $provider->expects($this->once())
            ->method('getServerReports')
            ->with(
                'eu-west1-a',
                'server-123',
                CloudReportPeriod::OneHour,
            )
            ->willReturn($expected);

        $result = (new GetCloudServerReportsAction(
            reports: $provider,
        ))->execute(
            region: 'eu-west1-a',
            serverId: 'server-123',
            period: CloudReportPeriod::OneHour,
        );

        $this->assertSame(
            $expected,
            $result,
        );
    }

    private static function emptyChart(): CloudReportChartData
    {
        return new CloudReportChartData(
            timestamps: [],
            series: [],
        );
    }
}
