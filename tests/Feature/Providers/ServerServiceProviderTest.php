<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domain\Server\DTOs\OperatingSystemInfo;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use Tests\TestCase;

final class ServerServiceProviderTest extends TestCase
{
    public function test_supported_operating_system_policy_is_built_from_config(): void
    {
        config()->set(
            'supported_os.matrix',
            [
                'ubuntu' => [
                    '24.04',
                ],

                'debian' => [
                    '12',
                ],
            ],
        );

        /*
         * The policy is registered as a singleton. Forget any instance
         * that may already have been resolved during the test bootstrap
         * so this assertion uses the config defined above.
         */
        $this->app->forgetInstance(
            SupportedOperatingSystemPolicy::class,
        );

        $policy = $this->app->make(
            SupportedOperatingSystemPolicy::class,
        );

        $this->assertTrue(
            $policy->supportsIdVersion(
                id: 'ubuntu',
                versionId: '24.04',
            ),
        );

        $this->assertTrue(
            $policy->supportsIdVersion(
                id: 'debian',
                versionId: '12',
            ),
        );

        $this->assertFalse(
            $policy->supportsIdVersion(
                id: 'ubuntu',
                versionId: '26.04',
            ),
        );
    }

    public function test_runtime_operating_system_validation_uses_configured_matrix(): void
    {
        config()->set(
            'supported_os.matrix',
            [
                'ubuntu' => [
                    '24.04',
                ],
            ],
        );

        $this->app->forgetInstance(
            SupportedOperatingSystemPolicy::class,
        );

        $policy = $this->app->make(
            SupportedOperatingSystemPolicy::class,
        );

        $supported = new OperatingSystemInfo(
            id: 'ubuntu',
            name: 'Ubuntu',
            versionId: '24.04',
            prettyName: 'Ubuntu 24.04 LTS',
        );

        $unsupportedVersion = new OperatingSystemInfo(
            id: 'ubuntu',
            name: 'Ubuntu',
            versionId: '22.04',
            prettyName: 'Ubuntu 22.04 LTS',
        );

        $this->assertTrue(
            $policy->supports(
                $supported,
            ),
        );

        $this->assertFalse(
            $policy->supports(
                $unsupportedVersion,
            ),
        );
    }
}
