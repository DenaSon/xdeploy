<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Platform\Support;

use App\Domain\Platform\Support\SupportedPlatformOperatingSystems;
use App\Domain\Server\DTOs\OperatingSystemInfo;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SupportedPlatformOperatingSystemsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function supportedDockerStackOperatingSystems(): iterable
    {
        yield 'debian 12' => [
            'debian',
            '12',
            'Debian GNU/Linux',
        ];

        yield 'ubuntu 22.04' => [
            'ubuntu',
            '22.04',
            'Ubuntu',
        ];

        yield 'ubuntu 24.04' => [
            'ubuntu',
            '24.04',
            'Ubuntu',
        ];

        yield 'ubuntu 26.04' => [
            'ubuntu',
            '26.04',
            'Ubuntu',
        ];
    }

    #[DataProvider('supportedDockerStackOperatingSystems')]
    public function test_it_supports_every_operating_system_offered_for_cloud_servers(
        string $id,
        string $version,
        string $name,
    ): void {
        $this->assertTrue(
            SupportedPlatformOperatingSystems::supportsDockerStack(
                new OperatingSystemInfo(
                    id: $id,
                    name: $name,
                    versionId: $version,
                    prettyName: null,
                ),
            ),
        );
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unsupportedDockerStackOperatingSystems(): iterable
    {
        yield 'debian 11' => [
            'debian',
            '11',
            'Debian GNU/Linux',
        ];

        yield 'ubuntu 20.04' => [
            'ubuntu',
            '20.04',
            'Ubuntu',
        ];

        yield 'unknown distro' => [
            'alpine',
            '3.22',
            'Alpine Linux',
        ];
    }

    #[DataProvider('unsupportedDockerStackOperatingSystems')]
    public function test_it_rejects_operating_system_versions_outside_the_supported_matrix(
        string $id,
        string $version,
        string $name,
    ): void {
        $this->assertFalse(
            SupportedPlatformOperatingSystems::supportsDockerStack(
                new OperatingSystemInfo(
                    id: $id,
                    name: $name,
                    versionId: $version,
                    prettyName: null,
                ),
            ),
        );
    }
}
