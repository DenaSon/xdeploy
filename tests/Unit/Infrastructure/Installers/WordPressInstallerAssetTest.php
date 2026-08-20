<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Installers;

use Tests\TestCase;

final class WordPressInstallerAssetTest extends TestCase
{
    public function test_wordpress_installer_matches_the_pinned_checksum(): void
    {
        $path = public_path(
            'assets/installers/wordpress/docker.sh',
        );

        self::assertFileExists(
            $path,
        );

        self::assertSame(
            (string) config(
                'xdeploy.installers.wordpress.docker.sha256',
            ),
            hash_file(
                'sha256',
                $path,
            ),
        );
    }

    public function test_installer_uses_pinned_official_images_and_persistent_storage(): void
    {
        $contents = $this->installerContents();

        self::assertStringContainsString(
            'wordpress:7.0.4-php8.3-apache@sha256:b427cec767f5de2aa649390cb8805aa1fe320e1e0d57fc1f467754edb6cc0a49',
            $contents,
        );
        self::assertStringContainsString(
            'mariadb:11.4.12@sha256:4f1d8d202fcf7bcb3902f63af09f9c1a050c2922a89652f22abaec0d4f015e83',
            $contents,
        );
        self::assertStringNotContainsString(
            'wordpress:latest',
            $contents,
        );
        self::assertStringNotContainsString(
            'mariadb:latest',
            $contents,
        );
        self::assertStringContainsString(
            'xdeploy-wordpress-data',
            $contents,
        );
        self::assertStringContainsString(
            'xdeploy-wordpress-database',
            $contents,
        );
        self::assertStringContainsString(
            'wordpress_data:/var/www/html',
            $contents,
        );
        self::assertStringContainsString(
            'database_data:/var/lib/mysql',
            $contents,
        );
    }

    public function test_installer_keeps_wordpress_private_and_database_internal(): void
    {
        $contents = $this->installerContents();

        self::assertStringContainsString(
            '127.0.0.1:8080:80',
            $contents,
        );
        self::assertStringNotContainsString(
            '0.0.0.0:8080:80',
            $contents,
        );
        self::assertStringNotContainsString(
            '3306:3306',
            $contents,
        );
        self::assertStringNotContainsString(
            'caddy',
            strtolower($contents),
        );
    }

    public function test_installer_exposes_a_managed_reverse_proxy_configuration_hook(): void
    {
        $contents = $this->installerContents();

        self::assertStringContainsString(
            'XDEPLOY_WORDPRESS_PUBLIC_URL: \${XDEPLOY_WORDPRESS_PUBLIC_URL:-}',
            $contents,
        );
        self::assertStringContainsString(
            'WORDPRESS_CONFIG_EXTRA: >-',
            $contents,
        );
        self::assertStringContainsString(
            "getenv('XDEPLOY_WORDPRESS_PUBLIC_URL')",
            $contents,
        );
        self::assertStringContainsString(
            'define(\'WP_HOME\', \$\$xdeployPublicUrl);',
            $contents,
        );
        self::assertStringContainsString(
            'define(\'WP_SITEURL\', \$\$xdeployPublicUrl);',
            $contents,
        );
        self::assertStringContainsString(
            "define('FORCE_SSL_ADMIN', true);",
            $contents,
        );
        self::assertStringNotContainsString(
            'wp-config.php',
            $contents,
        );
    }

    public function test_installer_generates_and_preserves_private_database_credentials(): void
    {
        $contents = $this->installerContents();

        self::assertStringContainsString(
            'if [[ -f "$ENV_FILE" ]]',
            $contents,
        );
        self::assertStringContainsString(
            'openssl rand -hex 32',
            $contents,
        );
        self::assertStringContainsString(
            'chmod 0600 "$ENV_FILE"',
            $contents,
        );
        self::assertStringContainsString(
            'WORDPRESS_DB_PASSWORD=${wordpress_db_password}',
            $contents,
        );
        self::assertStringContainsString(
            'MARIADB_ROOT_PASSWORD=${mariadb_root_password}',
            $contents,
        );
        self::assertStringContainsString(
            'must contain each required key exactly once',
            $contents,
        );
        self::assertStringContainsString(
            'docker volume inspect "$DATABASE_VOLUME"',
            $contents,
        );
        self::assertStringContainsString(
            'cannot be reused without its xDeploy environment file',
            $contents,
        );
        self::assertStringNotContainsString(
            'WORDPRESS_DB_PASSWORD=wordpress',
            $contents,
        );
        self::assertStringNotContainsString(
            'MARIADB_ROOT_PASSWORD=root',
            $contents,
        );
    }

    public function test_installer_verifies_both_service_health_checks_before_completion(): void
    {
        $contents = $this->installerContents();

        self::assertStringContainsString(
            'healthcheck.sh',
            $contents,
        );
        self::assertStringContainsString(
            '--innodb_initialized',
            $contents,
        );
        self::assertStringContainsString(
            'condition: service_healthy',
            $contents,
        );
        self::assertStringContainsString(
            '@mysqli_connect("database"',
            $contents,
        );
        self::assertStringContainsString(
            '@fsockopen("127.0.0.1", 80)',
            $contents,
        );
        self::assertStringContainsString(
            '/var/www/html/wp-includes/version.php',
            $contents,
        );
        self::assertStringContainsString(
            'service_health database',
            $contents,
        );
        self::assertStringContainsString(
            'service_health wordpress',
            $contents,
        );
        self::assertStringContainsString(
            '[[ "$database_health" == "healthy" ]]',
            $contents,
        );
        self::assertStringContainsString(
            '[[ "$wordpress_health" == "healthy" ]]',
            $contents,
        );
        self::assertStringContainsString(
            'touch "$MARKER_FILE"',
            $contents,
        );
    }

    public function test_installer_reports_failure_stages_and_bounds_remote_waits(): void
    {
        $contents = $this->installerContents();

        foreach (
            [
                "stage='prerequisites'",
                "stage='environment_write'",
                "stage='compose_config_write'",
                "stage='compose_validation'",
                "stage='image_pull'",
                "stage='compose_up'",
                "stage='runtime_wait'",
                "stage='runtime_verify'",
            ] as $stage
        ) {
            self::assertStringContainsString(
                $stage,
                $contents,
            );
        }

        self::assertStringContainsString(
            "printf '[xDeploy][WordPress][error] stage=%s exit_code=%s\\n'",
            $contents,
        );
        self::assertStringContainsString(
            'readonly IMAGE_PULL_TIMEOUT_SECONDS=300',
            $contents,
        );
        self::assertStringContainsString(
            'readonly IMAGE_PULL_KILL_AFTER_SECONDS=10',
            $contents,
        );
        self::assertStringContainsString(
            'readonly RUNTIME_WAIT_ATTEMPTS=60',
            $contents,
        );
        self::assertStringContainsString(
            '--kill-after="${IMAGE_PULL_KILL_AFTER_SECONDS}s"',
            $contents,
        );
    }

    private function installerContents(): string
    {
        $contents = file_get_contents(
            public_path(
                'assets/installers/wordpress/docker.sh',
            ),
        );

        self::assertIsString(
            $contents,
        );

        return $contents;
    }
}
