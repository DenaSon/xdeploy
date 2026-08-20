<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\WordPress;

use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Enums\SoftwareType;
use App\Domain\Application\WordPress\WordPressApplication;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use Tests\TestCase;

final class WordPressApplicationTest extends TestCase
{
    public function test_it_exposes_wordpress_capability_and_healthy_multi_service_runtime(): void
    {
        $ssh = new WordPressApplicationFakeSshConnection;
        $application = $this->application(
            ssh: $ssh,
            installerSource: new WordPressApplicationFakeInstallerSource,
        );

        $info = $application->inspect();

        self::assertSame(
            ApplicationType::WordPress,
            $application->type(),
        );
        self::assertSame(
            'WordPress',
            $application->name(),
        );
        self::assertSame(
            ApplicationState::Running,
            $info->state,
        );
        self::assertSame(
            '7.0.4',
            $info->version(),
        );
        self::assertSame(
            [
                'curl',
                'ca-certificates',
                'openssl',
            ],
            $application->requirements()->systemPackages,
        );
        self::assertSame(
            [
                PlatformType::DockerCompose,
            ],
            $application->requirements()->platforms,
        );
        self::assertCount(
            1,
            $application->provides(),
        );
        self::assertSame(
            SoftwareType::WordPress,
            $application->provides()[0]->type,
        );
        self::assertSame(
            [
                'database',
                'wordpress',
            ],
            $ssh->inspectedServices,
        );
        self::assertSame(
            [
                'database',
                'wordpress',
            ],
            $ssh->healthCheckedServices,
        );
    }

    public function test_unhealthy_database_prevents_running_state(): void
    {
        $ssh = new WordPressApplicationFakeSshConnection(
            healthByService: [
                'database' => 'unhealthy',
                'wordpress' => 'healthy',
            ],
        );

        $application = $this->application(
            ssh: $ssh,
            installerSource: new WordPressApplicationFakeInstallerSource,
        );

        self::assertSame(
            ApplicationState::Unknown,
            $application->inspect()->state,
        );
    }

    public function test_install_uses_the_verified_wordpress_installer_as_sensitive_input(): void
    {
        config()->set(
            'xdeploy.installers.wordpress.docker.path',
            'wordpress/docker.sh',
        );
        config()->set(
            'xdeploy.installers.wordpress.docker.sha256',
            str_repeat('a', 64),
        );

        $ssh = new WordPressApplicationFakeSshConnection;
        $installerSource = new WordPressApplicationFakeInstallerSource;
        $application = $this->application(
            ssh: $ssh,
            installerSource: $installerSource,
        );

        $application->install();

        self::assertSame(
            'wordpress/docker.sh',
            $installerSource->relativePath,
        );
        self::assertSame(
            str_repeat('a', 64),
            $installerSource->expectedSha256,
        );
        self::assertContains(
            'wordpress-installer-command',
            $ssh->sensitiveCommands,
        );
    }

    public function test_lifecycle_controls_only_the_wordpress_compose_project(): void
    {
        $ssh = new WordPressApplicationFakeSshConnection;
        $application = $this->application(
            ssh: $ssh,
            installerSource: new WordPressApplicationFakeInstallerSource,
        );

        $application->start();

        $commands = implode(
            "\n",
            $ssh->commands,
        );

        self::assertStringContainsString(
            '-f /opt/xdeploy/apps/wordpress/docker-compose.yml',
            $commands,
        );
        self::assertStringContainsString(
            '-p xdeploy-wordpress',
            $commands,
        );
        self::assertStringContainsString(
            'up -d --remove-orphans',
            $commands,
        );
        self::assertStringNotContainsString(
            'caddy',
            strtolower($commands),
        );
    }

    public function test_uninstall_removes_runtime_without_deleting_persistent_volumes(): void
    {
        $ssh = new WordPressApplicationFakeSshConnection;
        $application = $this->application(
            ssh: $ssh,
            installerSource: new WordPressApplicationFakeInstallerSource,
        );

        $application->uninstall();

        $commands = implode(
            "\n",
            $ssh->commands,
        );

        self::assertStringContainsString(
            'down --remove-orphans',
            $commands,
        );
        self::assertStringContainsString(
            'rm -f /opt/xdeploy/apps/wordpress/.xdeploy-install-complete',
            $commands,
        );
        self::assertStringNotContainsString(
            'down --volumes',
            $commands,
        );
        self::assertStringNotContainsString(
            'down -v',
            $commands,
        );
    }

    private function application(
        SSHConnectionInterface $ssh,
        InstallerSourceInterface $installerSource,
    ): WordPressApplication {
        return new WordPressApplication(
            ssh: $ssh,
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    ssh: $ssh,
                ),
            ),
            installerSource: $installerSource,
        );
    }
}

final class WordPressApplicationFakeInstallerSource implements InstallerSourceInterface
{
    public ?string $relativePath = null;

    public ?string $expectedSha256 = null;

    public function buildExecutionCommand(
        string $relativePath,
        string $expectedSha256,
    ): string {
        $this->relativePath = $relativePath;
        $this->expectedSha256 = $expectedSha256;

        return 'wordpress-installer-command';
    }
}

final class WordPressApplicationFakeSshConnection implements SSHConnectionInterface
{
    /** @var list<string> */
    public array $commands = [];

    /** @var list<string> */
    public array $sensitiveCommands = [];

    /** @var list<string> */
    public array $inspectedServices = [];

    /** @var list<string> */
    public array $healthCheckedServices = [];

    /**
     * @param  array<string, string>  $healthByService
     */
    public function __construct(
        private readonly array $healthByService = [
            'database' => 'healthy',
            'wordpress' => 'healthy',
        ],
    ) {}

    public function connect(Server $server): bool
    {
        return true;
    }

    public function execute(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
    ): string {
        return $this->executeWithResult(
            command: $command,
            timeout: $timeout,
        )->output;
    }

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
        bool $sensitive = false,
    ): SSHResult {
        $this->commands[] = $command;

        if ($sensitive) {
            $this->sensitiveCommands[] = $command;
        }

        if (trim($command) === 'id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        if (
            str_contains(
                $command,
                "marker='/opt/xdeploy/apps/wordpress/.xdeploy-install-complete'",
            )
        ) {
            return new SSHResult(
                output: '7.0.4',
                exitCode: 0,
            );
        }

        if (
            str_contains(
                $command,
                '.State.Health.Status',
            )
        ) {
            $service = $this->composeServiceFromCommand(
                $command,
            );

            if (is_string($service)) {
                $this->healthCheckedServices[] = $service;
            }

            return new SSHResult(
                output: is_string($service)
                    ? ($this->healthByService[$service] ?? 'none')
                    : 'none',
                exitCode: 0,
            );
        }

        if (str_contains($command, 'docker ps')) {
            $service = $this->composeServiceFromCommand(
                $command,
            );

            if (is_string($service)) {
                $this->inspectedServices[] = $service;
            }

            return new SSHResult(
                output: is_string($service)
                    ? 'container-'.$service
                    : '',
                exitCode: 0,
            );
        }

        return new SSHResult(
            output: '',
            exitCode: 0,
        );
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void {}

    private function composeServiceFromCommand(
        string $command,
    ): ?string {
        preg_match(
            '/label=com\\.docker\\.compose\\.service=([^"]+)/',
            $command,
            $matches,
        );

        $service = $matches[1] ?? null;

        return is_string($service)
            ? $service
            : null;
    }
}
