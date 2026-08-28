<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ParsPack;

use Tests\TestCase;

final class ParsPackConfigurationTest extends TestCase
{
    public function test_catalog_and_order_selections_are_not_static_provider_defaults(): void
    {
        $config = config('parspack');

        $this->assertIsArray($config);
        $this->assertArrayNotHasKey('region', $config);

        $defaults = $config['defaults'] ?? null;

        $this->assertIsArray($defaults);
        $this->assertArrayNotHasKey('size_id', $defaults);
        $this->assertArrayNotHasKey('image_id', $defaults);
        $this->assertArrayNotHasKey('username', $defaults);
        $this->assertArrayNotHasKey('disk_size', $defaults);
        $this->assertArrayHasKey('init_script', $defaults);
        $this->assertArrayHasKey('ha_enabled', $defaults);
    }

    public function test_env_example_does_not_expose_obsolete_catalog_selection_keys(): void
    {
        $contents = file_get_contents(base_path('.env.example'));

        $this->assertIsString($contents);

        foreach ([
            'PARSPACK_CLOUD_REGION=',
            'PARSPACK_CLOUD_DEFAULT_SIZE_ID=',
            'PARSPACK_CLOUD_DEFAULT_IMAGE_ID=',
            'PARSPACK_CLOUD_DEFAULT_USERNAME=',
            'PARSPACK_CLOUD_DEFAULT_DISK_SIZE=',
        ] as $obsoleteKey) {
            $this->assertStringNotContainsString($obsoleteKey, $contents);
        }
    }
}
