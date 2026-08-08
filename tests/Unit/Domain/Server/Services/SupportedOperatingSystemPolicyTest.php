<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Server\Services;

use App\Domain\Server\DTOs\OperatingSystemInfo;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use PHPUnit\Framework\TestCase;

final class SupportedOperatingSystemPolicyTest extends TestCase
{
    private const array MATRIX = [
        'ubuntu' => [
            '24.04',
        ],
    ];

    public function test_it_supports_operating_system_present_in_matrix(): void
    {
        $policy = $this->policy();

        $operatingSystem = new OperatingSystemInfo(
            id: 'ubuntu',
            name: 'Ubuntu',
            versionId: '24.04',
            prettyName: 'Ubuntu 24.04 LTS',
        );

        $this->assertTrue(
            $policy->supports(
                $operatingSystem,
            ),
        );
    }

    public function test_it_normalizes_distribution_id_before_matching(): void
    {
        $policy = $this->policy();

        $this->assertTrue(
            $policy->supportsIdVersion(
                id: ' Ubuntu ',
                versionId: '24.04',
            ),
        );
    }

    public function test_it_rejects_version_not_present_in_matrix(): void
    {
        $policy = $this->policy();

        $this->assertFalse(
            $policy->supportsIdVersion(
                id: 'ubuntu',
                versionId: '22.04',
            ),
        );

        $this->assertFalse(
            $policy->supportsIdVersion(
                id: 'ubuntu',
                versionId: '26.04',
            ),
        );
    }

    public function test_it_rejects_distribution_not_present_in_matrix(): void
    {
        $policy = $this->policy();

        $this->assertFalse(
            $policy->supportsIdVersion(
                id: 'debian',
                versionId: '12',
            ),
        );
    }

    public function test_it_rejects_missing_distribution_or_version(): void
    {
        $policy = $this->policy();

        $this->assertFalse(
            $policy->supportsIdVersion(
                id: '',
                versionId: '24.04',
            ),
        );

        $this->assertFalse(
            $policy->supportsIdVersion(
                id: 'ubuntu',
                versionId: null,
            ),
        );

        $this->assertFalse(
            $policy->supportsIdVersion(
                id: 'ubuntu',
                versionId: '',
            ),
        );
    }

    public function test_it_exposes_supported_distribution_ids(): void
    {
        $policy = new SupportedOperatingSystemPolicy(
            matrix: [
                'ubuntu' => [
                    '24.04',
                ],

                'debian' => [
                    '12',
                ],
            ],
        );

        $this->assertSame(
            [
                'ubuntu',
                'debian',
            ],
            $policy->supportedIds(),
        );
    }

    public function test_it_exposes_supported_versions_for_distribution(): void
    {
        $policy = new SupportedOperatingSystemPolicy(
            matrix: [
                'ubuntu' => [
                    '22.04',
                    '24.04',
                ],
            ],
        );

        $this->assertSame(
            [
                '22.04',
                '24.04',
            ],
            $policy->supportedVersions(
                'Ubuntu',
            ),
        );

        $this->assertSame(
            [],
            $policy->supportedVersions(
                'debian',
            ),
        );
    }

    private function policy(): SupportedOperatingSystemPolicy
    {
        return new SupportedOperatingSystemPolicy(
            matrix: self::MATRIX,
        );
    }
}
