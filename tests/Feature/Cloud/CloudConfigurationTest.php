<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use Tests\TestCase;

final class CloudConfigurationTest extends TestCase
{
    public function test_default_cloud_provider_is_configured(): void
    {
        $this->assertSame(
            'arvan',
            config('cloud.default'),
        );
    }

    public function test_provider_availability_defaults_are_explicit(): void
    {
        $this->assertTrue(
            config('cloud.providers.arvan.enabled'),
        );
        $this->assertTrue(
            config('cloud.providers.arvan.purchase_enabled'),
        );
        $this->assertFalse(
            config('cloud.providers.liara.enabled'),
        );
        $this->assertFalse(
            config('cloud.providers.liara.purchase_enabled'),
        );
    }

    public function test_arvan_cloud_configuration_exists(): void
    {
        $configuration = config('cloud.providers.arvan');

        $this->assertIsArray($configuration);

        $this->assertArrayHasKey('enabled', $configuration);
        $this->assertArrayHasKey('purchase_enabled', $configuration);
        $this->assertArrayHasKey('base_url', $configuration);
        $this->assertArrayHasKey('api_key', $configuration);
        $this->assertArrayHasKey('region', $configuration);
        $this->assertArrayHasKey('timeouts', $configuration);
        $this->assertArrayHasKey('package_repositories', $configuration);
        $this->assertArrayHasKey('defaults', $configuration);
    }

    public function test_arvan_cloud_timeouts_are_integers(): void
    {
        $this->assertIsInt(
            config('cloud.providers.arvan.timeouts.connect'),
        );

        $this->assertIsInt(
            config('cloud.providers.arvan.timeouts.request'),
        );
    }

    public function test_purchase_catalog_has_a_bounded_transport_budget(): void
    {
        $connectTimeout = config(
            'cloud.catalog_cache.timeouts.connect',
        );
        $requestTimeout = config(
            'cloud.catalog_cache.timeouts.request',
        );

        $this->assertIsInt($connectTimeout);
        $this->assertIsInt($requestTimeout);
        $this->assertGreaterThan(0, $connectTimeout);
        $this->assertGreaterThanOrEqual(
            $connectTimeout,
            $requestTimeout,
        );
        $this->assertLessThanOrEqual(10, $requestTimeout);
    }

    public function test_arvan_ubuntu_package_mirror_is_https(): void
    {
        $mirror = config(
            'cloud.providers.arvan.package_repositories.ubuntu_mirror',
        );

        $this->assertIsString($mirror);
        $this->assertStringStartsWith(
            'https://',
            $mirror,
        );
    }

    public function test_arvan_cloud_defaults_are_configured(): void
    {
        $defaults = config('cloud.providers.arvan.defaults');

        $this->assertIsArray($defaults);

        $this->assertArrayHasKey('size_id', $defaults);
        $this->assertArrayHasKey('image_id', $defaults);
        $this->assertArrayHasKey('network_id', $defaults);
        $this->assertArrayHasKey('security_group_id', $defaults);
        $this->assertArrayHasKey('security_group_name', $defaults);
        $this->assertArrayHasKey('create_type', $defaults);
    }
}
