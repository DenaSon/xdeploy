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
            'INSTALLER_TIMEOUT_SECONDS=1800',
            $script,
        );

        $this->assertStringContainsString(
            'DPkg::Lock::Timeout=',
            $script,
        );

        $this->assertStringContainsString(
            'Acquire::ForceIPv4=true',
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
            '--max-time 30',
            $script,
        );

        $this->assertStringContainsString(
            '[xDeploy][docker][error] stage=%s exit_code=%s',
            $script,
        );

        $this->assertStringContainsString(
            "stage='docker_ce_package_download'",
            $script,
        );

        $this->assertStringContainsString(
            "stage='install_docker_ce'",
            $script,
        );

        $this->assertStringContainsString(
            'docker buildx version',
            $script,
        );
    }

    public function test_docker_installer_uses_a_safe_ubuntu_repository_fallback(): void
    {
        $script = file_get_contents(
            public_path(
                'assets/installers/docker/debian-family.sh',
            ),
        );

        $this->assertIsString($script);

        $this->assertStringContainsString(
            "XDEPLOY_DOCKER_KEYRING='/etc/apt/keyrings/xdeploy-docker.asc'",
            $script,
        );

        $this->assertStringContainsString(
            "XDEPLOY_DOCKER_SOURCE='/etc/apt/sources.list.d/xdeploy-docker.list'",
            $script,
        );

        $this->assertStringContainsString(
            'install_ubuntu_repository_fallback()',
            $script,
        );

        $this->assertStringContainsString(
            'docker.io',
            $script,
        );

        $this->assertStringContainsString(
            'docker-compose-v2',
            $script,
        );

        $this->assertStringContainsString(
            'docker-buildx',
            $script,
        );

        $this->assertStringContainsString(
            'docker_ce_package_preflight',
            $script,
        );

        $this->assertStringContainsString(
            'apt_get install --dry-run --no-install-recommends',
            $script,
        );

        $this->assertStringContainsString(
            "stage='ubuntu_fallback_package_download'",
            $script,
        );

        $this->assertStringContainsString(
            "stage='ubuntu_fallback_package_install'",
            $script,
        );

        $this->assertStringContainsString(
            '--download-only',
            $script,
        );

        $this->assertStringContainsString(
            '--no-download',
            $script,
        );

        $this->assertStringContainsString(
            'if [[ "$ID" == \'ubuntu\' ]]; then',
            $script,
        );

        $this->assertStringContainsString(
            'Refusing Ubuntu Docker fallback because Docker CE packages are already installed.',
            $script,
        );
    }
}
