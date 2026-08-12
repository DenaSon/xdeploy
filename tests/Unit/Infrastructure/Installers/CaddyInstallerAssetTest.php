<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Installers;

use Tests\TestCase;

final class CaddyInstallerAssetTest extends TestCase
{
    public function test_caddy_installer_matches_the_pinned_checksum(): void
    {
        $path = public_path(
            'assets/installers/caddy/debian-family.sh',
        );

        $this->assertFileExists(
            $path,
        );

        $this->assertSame(
            (string) config(
                'xdeploy.installers.caddy.debian_family.sha256',
            ),
            hash_file(
                'sha256',
                $path,
            ),
        );
    }

    public function test_caddy_installer_bootstraps_an_xdeploy_managed_import_root(): void
    {
        $contents = file_get_contents(
            public_path(
                'assets/installers/caddy/debian-family.sh',
            ),
        );

        $this->assertIsString(
            $contents,
        );

        $this->assertStringContainsString(
            '# xDeploy: caddy-platform',
            $contents,
        );

        $this->assertStringContainsString(
            'import xdeploy/sites/*.caddy',
            $contents,
        );

        $this->assertStringContainsString(
            'caddy validate',
            $contents,
        );

        $this->assertStringContainsString(
            'systemctl enable --now caddy',
            $contents,
        );

        $this->assertStringNotContainsString(
            'curl | sh',
            $contents,
        );
    }
}
