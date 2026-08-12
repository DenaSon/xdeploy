<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\Marzban;

use App\Domain\Application\Marzban\MarzbanApplication;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class MarzbanApplicationLifecycleTest extends TestCase
{
    public function test_start_only_controls_the_base_marzban_compose_project(): void
    {
        $ssh = new MarzbanLifecycleFakeSshConnection;
        $application = $this->application($ssh);

        $application->start();

        $commands = implode("\n", $ssh->commands);

        self::assertStringContainsString(
            '-f /opt/marzban/docker-compose.yml',
            $commands,
        );
        self::assertStringContainsString(
            '-p marzban',
            $commands,
        );
        self::assertStringNotContainsString(
            'docker-compose.xdeploy.yml',
            $commands,
        );
        self::assertStringNotContainsString(
            '/opt/marzban/Caddyfile',
            $commands,
        );
        self::assertStringNotContainsString(
            'caddy',
            strtolower($commands),
        );
    }

    private function application(
        SSHConnectionInterface $ssh,
    ): MarzbanApplication {
        return new MarzbanApplication(
            ssh: $ssh,
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    ssh: $ssh,
                ),
            ),
            installerSource: new MarzbanLifecycleFakeInstallerSource,
        );
    }
}

final class MarzbanLifecycleFakeInstallerSource implements InstallerSourceInterface
{
    public function buildExecutionCommand(
        string $relativePath,
        string $expectedSha256,
    ): string {
        return 'true';
    }
}

final class MarzbanLifecycleFakeSshConnection implements SSHConnectionInterface
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
