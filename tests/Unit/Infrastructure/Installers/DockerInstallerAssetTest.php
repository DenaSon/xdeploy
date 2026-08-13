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
            "stage='ubuntu_package_download'",
            $script,
        );

        $this->assertStringContainsString(
            "stage='docker_ce_package_download'",
            $script,
        );

        $this->assertStringContainsString(
            'docker buildx version',
            $script,
        );
    }

    public function test_docker_installer_prefers_ubuntu_repository_packages(): void
    {
        $script = file_get_contents(
            public_path(
                'assets/installers/docker/debian-family.sh',
            ),
        );

        $this->assertIsString($script);

        $this->assertStringContainsString(
            'install_ubuntu_repository_packages()',
            $script,
        );

        $this->assertStringContainsString(
            'ubuntu_repository_package_set_available()',
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
            'if [[ "$ID" == \'ubuntu\' ]]; then',
            $script,
        );

        $this->assertStringContainsString(
            "stage='ubuntu_repository_cleanup'",
            $script,
        );

        $this->assertStringContainsString(
            "stage='ubuntu_repository_update'",
            $script,
        );

        $this->assertStringContainsString(
            "stage='ubuntu_package_preflight'",
            $script,
        );

        $this->assertStringContainsString(
            "stage='ubuntu_package_install'",
            $script,
        );

        $this->assertStringContainsString(
            'Refusing Ubuntu repository installation because Docker CE packages are already installed.',
            $script,
        );

        $this->assertStringContainsString(
            'Ubuntu Docker package set is unavailable; falling back to the official Docker repository.',
            $script,
        );

        $ubuntuPathPosition = strpos(
            $script,
            'if [[ "$ID" == \'ubuntu\' ]]; then',
        );
        $officialFallbackPosition = strrpos(
            $script,
            'install_official_docker_repository_packages',
        );

        $this->assertIsInt($ubuntuPathPosition);
        $this->assertIsInt($officialFallbackPosition);
        $this->assertLessThan(
            $officialFallbackPosition,
            $ubuntuPathPosition,
        );
    }

    public function test_docker_installer_keeps_the_official_repository_as_fallback(): void
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
            'install_official_docker_repository_packages()',
            $script,
        );

        $this->assertStringContainsString(
            'https://download.docker.com/linux/${ID}',
            $script,
        );

        $this->assertStringContainsString(
            'docker-ce',
            $script,
        );

        $this->assertStringContainsString(
            'docker-compose-plugin',
            $script,
        );

        $this->assertStringContainsString(
            'docker-buildx-plugin',
            $script,
        );

        $this->assertStringContainsString(
            "stage='docker_ce_package_preflight'",
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
            '--download-only',
            $script,
        );

        $this->assertStringContainsString(
            '--no-download',
            $script,
        );
    }
}
