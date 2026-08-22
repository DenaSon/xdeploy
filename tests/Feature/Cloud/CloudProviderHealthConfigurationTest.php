<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use Tests\TestCase;

final class CloudProviderHealthConfigurationTest extends TestCase
{
    public function test_health_thresholds_are_bounded_and_ordered(): void
    {
        $degraded = config(
            'cloud_health.thresholds.degraded_after_failures',
        );
        $unavailable = config(
            'cloud_health.thresholds.unavailable_after_failures',
        );
        $recovery = config(
            'cloud_health.thresholds.recovery_successes',
        );

        $this->assertIsInt($degraded);
        $this->assertIsInt($unavailable);
        $this->assertIsInt($recovery);
        $this->assertGreaterThan(0, $degraded);
        $this->assertGreaterThanOrEqual($degraded, $unavailable);
        $this->assertGreaterThan(0, $recovery);
    }

    public function test_health_state_storage_defaults_are_positive(): void
    {
        $ttl = config('cloud_health.state_ttl_seconds');
        $lock = config('cloud_health.lock_seconds');
        $wait = config('cloud_health.lock_wait_seconds');

        $this->assertIsInt($ttl);
        $this->assertIsInt($lock);
        $this->assertIsInt($wait);
        $this->assertGreaterThan(0, $ttl);
        $this->assertGreaterThan(0, $lock);
        $this->assertGreaterThanOrEqual(0, $wait);
    }

    public function test_health_probe_is_enabled_by_default(): void
    {
        $this->assertTrue(
            config('cloud_health.probe.enabled'),
        );
    }
}
