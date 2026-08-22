<?php

declare(strict_types=1);

namespace Tests\Unit\Deployment;

use Tests\TestCase;

final class RuntimePermissionsComposerHookTest extends TestCase
{
    public function test_composer_runs_runtime_permission_repair_after_install_and_update(): void
    {
        $composer = json_decode(
            (string) file_get_contents(
                base_path('composer.json'),
            ),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            [
                '@php deployment/fix-runtime-permissions.php',
            ],
            $composer['scripts']['runtime:permissions'] ?? null,
        );

        self::assertContains(
            '@runtime:permissions',
            $composer['scripts']['post-install-cmd'] ?? [],
        );

        self::assertContains(
            '@runtime:permissions',
            $composer['scripts']['post-update-cmd'] ?? [],
        );

        self::assertFileExists(
            base_path(
                'deployment/fix-runtime-permissions.php',
            ),
        );
    }
}
