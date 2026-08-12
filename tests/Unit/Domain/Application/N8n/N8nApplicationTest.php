<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\N8n;

use App\Domain\Application\N8n\N8nApplication;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Enums\SoftwareType;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use Tests\TestCase;

final class N8nApplicationTest extends TestCase
{
    public function test_it_exposes_n8n_capability_and_runtime_version(): void
    {
        $ssh = new N8nApplicationFakeSshConnection;
        $application = $this->application(
            ssh: $ssh,
            installerSource: new N8nApplicationFakeInstallerSource,
        );

        $info = $application->inspect();

        $this->assertSame(
            ApplicationType::N8n,
            $application->type(),
        );
        $this->assertSame(
            'n8n',
            $application->name(),
        );
        $this->assertSame(
            ApplicationState::Running,
            $info->state,
        );
        $this->assertSame(
            '2.32.6',
            $info->version(),
        );
        $this->assertSame(
            [
                'curl',
                'ca-certificates',
            ],
            $application->requirements()->systemPackages,
        );
        $this->assertSame(
            [
                PlatformType::DockerCompose,
            ],
            $application->requirements()->platforms,
        );
        $this->assertCount(
            1,
            $application->provides(),
        );
        $this->assertSame(
            SoftwareType::N8n,
            $application->provides()[0]->type,
        );
    }

    public function test_install_uses_the_verified_n8n_installer_asset(): void
    {
        config()->set(
            'xdeploy.installers.n8n.docker.path',
            'n8n/docker.sh',
        );
        config()->set(
            'xdeploy.installers.n8n.docker.sha256',
            str_repeat('a', 64),
        );

        $ssh = new N8nApplicationFakeSshConnection;
        $installerSource = new N8nApplicationFakeInstallerSource;
        $application = $this->application(
            ssh: $ssh,
            installerSource: $installerSource,
        );

        $application->install();

        $this->assertSame(
            'n8n/docker.sh',
            $installerSource->relativePath,
        );
        $this->assertSame(
            str_repeat('a', 64),
            $installerSource->expectedSha256,
        );
        $this->assertTrue(
            $this->commandsContain(
                $ssh->commands,
                'n8n-installer-command',
            ),
        );
    }

    public function test_lifecycle_only_controls_the_n8n_compose_project(): void
    {
        $ssh = new N8nApplicationFakeSshConnection;
        $application = $this->application(
            ssh: $ssh,
            installerSource: new N8nApplicationFakeInstallerSource,
        );

        $application->start();

        $commands = implode(
            "\n",
            $ssh->commands,
        );

        $this->assertStringContainsString(
            '-f /opt/n8n/docker-compose.yml',
            $commands,
        );
        $this->assertStringContainsString(
            '-p n8n',
            $commands,
        );
        $this->assertStringContainsString(
            'up -d --remove-orphans',
            $commands,
        );
        $this->assertStringNotContainsString(
            'caddy',
            strtolower($commands),
        );
        $this->assertStringNotContainsString(
            'marzban',
            strtolower($commands),
        );
    }

    private function application(
        SSHConnectionInterface $ssh,
        InstallerSourceInterface $installerSource,
    ): N8nApplication {
        return new N8nApplication(
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

    /**
     * @param  list<string>  $commands
     */
    private function commandsContain(
        array $commands,
        string $needle,
    ): bool {
        foreach ($commands as $command) {
            if (str_contains($command, $needle)) {
                return true;
            }
        }

        return false;
    }
}

final class N8nApplicationFakeInstallerSource implements InstallerSourceInterface
{
    public ?string $relativePath = null;

    public ?string $expectedSha256 = null;

    public function buildExecutionCommand(
        string $relativePath,
        string $expectedSha256,
    ): string {
        $this->relativePath = $relativePath;
        $this->expectedSha256 = $expectedSha256;

        return 'n8n-installer-command';
    }
}

final class N8nApplicationFakeSshConnection implements SSHConnectionInterface
{
    /**
     * @var list<string>
     */
    public array $commands = [];

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

        if (trim($command) === 'id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        if (
            str_contains(
                $command,
                "marker='/opt/n8n/.xdeploy-install-complete'",
            )
        ) {
            return new SSHResult(
                output: '2.32.6',
                exitCode: 0,
            );
        }

        if (str_contains($command, 'docker ps')) {
            return new SSHResult(
                output: 'container123',
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
}
