<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Installers;

use Tests\TestCase;

final class N8nInstallerAssetTest extends TestCase
{
    public function test_n8n_installer_matches_the_pinned_checksum(): void
    {
        $path = public_path(
            'assets/installers/n8n/docker.sh',
        );

        $this->assertFileExists(
            $path,
        );

        $this->assertSame(
            (string) config(
                'xdeploy.installers.n8n.docker.sha256',
            ),
            hash_file(
                'sha256',
                $path,
            ),
        );
    }

    public function test_n8n_installer_keeps_the_service_private_and_persistent(): void
    {
        $contents = file_get_contents(
            public_path(
                'assets/installers/n8n/docker.sh',
            ),
        );

        $this->assertIsString(
            $contents,
        );

        $this->assertStringContainsString(
            'docker.n8n.io/n8nio/n8n:2.32.6',
            $contents,
        );
        $this->assertStringNotContainsString(
            'docker.n8n.io/n8nio/n8n:latest',
            $contents,
        );
        $this->assertStringContainsString(
            '127.0.0.1:5678:5678',
            $contents,
        );
        $this->assertStringContainsString(
            'n8n_data:/home/node/.n8n',
            $contents,
        );
        $this->assertStringContainsString(
            'N8N_ENFORCE_SETTINGS_FILE_PERMISSIONS=true',
            $contents,
        );
        $this->assertStringNotContainsString(
            'N8N_WEBHOOK_URL=',
            $contents,
        );
        $this->assertStringNotContainsString(
            'N8N_PROXY_HOPS=',
            $contents,
        );
        $this->assertStringNotContainsString(
            'caddy',
            strtolower($contents),
        );
    }
}
