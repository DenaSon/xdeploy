<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Installers;

use Tests\TestCase;

final class DockerInstallerAssetTest extends TestCase
{
    public function test_docker_installer_matches_the_pinned_checksum(): void
    {
        $path = public_path(
            'assets/installers/docker/debian-family.sh',
        );

        $this->assertFileExists($path);

        $this->assertSame(
            (string) config(
                'xdeploy.installers.docker.debian_family.sha256',
            ),
            hash_file(
                'sha256',
                $path,
            ),
        );
    }

    public function test_docker_installer_bounds_remote_package_operations_and_reports_failure_stage(): void
    {
        $script = file_get_contents(
            public_path(
                'assets/installers/docker/debian-family.sh',
            ),
        );

        $this->assertIsString($script);

        $this->assertStringContainsString(
            'exec timeout',
            $script,
        );

        $this->assertStringContainsString(
            'INSTALLER_TIMEOUT_SECONDS=300',
            $script,
        );

        $this->assertStringContainsString(
            'DPkg::Lock::Timeout=',
            $script,
        );

        $this->assertStringContainsString(
            'Acquire::Retries=',
            $script,
        );

        $this->assertStringContainsString(
            'Acquire::https::Timeout=',
            $script,
        );

        $this->assertStringContainsString(
            '--max-time 60',
            $script,
        );

        $this->assertStringContainsString(
            '[xDeploy][docker][error] stage=%s exit_code=%s',
            $script,
        );

        $this->assertStringContainsString(
            'docker buildx version',
            $script,
        );
    }
}
