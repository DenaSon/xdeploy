<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\AmneziaWg;

use App\Domain\Application\AmneziaWg\AmneziaWgApplication;
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

final class AmneziaWgApplicationTest extends TestCase
{
    public function test_it_exposes_amneziawg_capability_and_runtime_version(): void
    {
        $ssh = new AmneziaWgApplicationFakeSshConnection;
        $application = $this->application(
            ssh: $ssh,
            installerSource: new AmneziaWgApplicationFakeInstallerSource,
        );

        $info = $application->inspect();

        $this->assertSame(
            ApplicationType::AmneziaWg,
            $application->type(),
        );
        $this->assertSame(
            'AmneziaWG',
            $application->name(),
        );
        $this->assertSame(
            ApplicationState::Running,
            $info->state,
        );
        $this->assertSame(
            '0.2.19',
            $info->version(),
        );
        $this->assertSame(
            [
                'curl',
                'ca-certificates',
                'iproute2',
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
            SoftwareType::AmneziaWg,
            $application->provides()[0]->type,
        );
    }

    public function test_install_uses_the_verified_amneziawg_installer_asset(): void
    {
        config()->set(
            'xdeploy.installers.amneziawg.docker.path',
            'amneziawg/docker.sh',
        );
        config()->set(
            'xdeploy.installers.amneziawg.docker.sha256',
            str_repeat('a', 64),
        );

        $ssh = new AmneziaWgApplicationFakeSshConnection;
        $installerSource = new AmneziaWgApplicationFakeInstallerSource;
        $application = $this->application(
            ssh: $ssh,
            installerSource: $installerSource,
        );

        $application->install();

        $this->assertSame(
            'amneziawg/docker.sh',
            $installerSource->relativePath,
        );
        $this->assertSame(
            str_repeat('a', 64),
            $installerSource->expectedSha256,
        );
        $this->assertTrue(
            $this->commandsContain(
                $ssh->commands,
                'amneziawg-installer-command',
            ),
        );
    }

    public function test_lifecycle_only_controls_the_amneziawg_compose_project(): void
    {
        $ssh = new AmneziaWgApplicationFakeSshConnection;
        $application = $this->application(
            ssh: $ssh,
            installerSource: new AmneziaWgApplicationFakeInstallerSource,
        );

        $application->start();

        $commands = implode(
            "\n",
            $ssh->commands,
        );

        $this->assertStringContainsString(
            '-f /opt/xdeploy/apps/amneziawg/docker-compose.yml',
            $commands,
        );
        $this->assertStringContainsString(
            '-p amneziawg',
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
            'public-endpoint',
            strtolower($commands),
        );
    }

    public function test_uninstall_preserves_persistent_amneziawg_state(): void
    {
        $ssh = new AmneziaWgApplicationFakeSshConnection;
        $application = $this->application(
            ssh: $ssh,
            installerSource: new AmneziaWgApplicationFakeInstallerSource,
        );

        $application->uninstall();

        $commands = implode(
            "\n",
            $ssh->commands,
        );

        $this->assertStringContainsString(
            'down --remove-orphans',
            $commands,
        );
        $this->assertStringContainsString(
            'rm -f /opt/xdeploy/apps/amneziawg/.xdeploy-install-complete',
            $commands,
        );
        $this->assertStringNotContainsString(
            'rm -rf /opt/xdeploy/apps/amneziawg/data',
            $commands,
        );
    }

    private function application(
        SSHConnectionInterface $ssh,
        InstallerSourceInterface $installerSource,
    ): AmneziaWgApplication {
        return new AmneziaWgApplication(
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

final class AmneziaWgApplicationFakeInstallerSource implements InstallerSourceInterface
{
    public ?string $relativePath = null;

    public ?string $expectedSha256 = null;

    public function buildExecutionCommand(
        string $relativePath,
        string $expectedSha256,
    ): string {
        $this->relativePath = $relativePath;
        $this->expectedSha256 = $expectedSha256;

        return 'amneziawg-installer-command';
    }
}

final class AmneziaWgApplicationFakeSshConnection implements SSHConnectionInterface
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
                "marker='/opt/xdeploy/apps/amneziawg/.xdeploy-install-complete'",
            )
        ) {
            return new SSHResult(
                output: '0.2.19',
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
