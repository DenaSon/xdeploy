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
        $contents = $this->installerContents();

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

    public function test_n8n_installer_reports_structured_failure_stages_and_bounds_image_pull(): void
    {
        $contents = $this->installerContents();

        foreach (
            [
                "stage='prerequisites'",
                "stage='compose_config_write'",
                "stage='environment_write'",
                "stage='compose_validation'",
                "stage='image_pull'",
                "stage='compose_up'",
                "stage='container_wait'",
                "stage='container_verify'",
            ] as $stage
        ) {
            $this->assertStringContainsString(
                $stage,
                $contents,
            );
        }

        $this->assertStringContainsString(
            "printf '[xDeploy][n8n][error] stage=%s exit_code=%s\\n'",
            $contents,
        );
        $this->assertStringContainsString(
            'readonly IMAGE_PULL_TIMEOUT_SECONDS=300',
            $contents,
        );
        $this->assertStringContainsString(
            'readonly IMAGE_PULL_KILL_AFTER_SECONDS=10',
            $contents,
        );
        $this->assertStringContainsString(
            '--signal=TERM',
            $contents,
        );
        $this->assertStringContainsString(
            '--kill-after="${IMAGE_PULL_KILL_AFTER_SECONDS}s"',
            $contents,
        );
        $this->assertStringNotContainsString(
            '--foreground',
            $contents,
        );
    }

    private function installerContents(): string
    {
        $contents = file_get_contents(
            public_path(
                'assets/installers/n8n/docker.sh',
            ),
        );

        $this->assertIsString(
            $contents,
        );

        return $contents;
    }
}
