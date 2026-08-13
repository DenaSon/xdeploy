<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\PublicEndpoint;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\Marzban\Https\SshMarzbanHttpsInterruptedOperationRecovery;
use App\Infrastructure\Application\N8n\PublicEndpoint\SshN8nPublicEndpointInterruptedOperationRecovery;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class InterruptedOperationRecoveryTest extends TestCase
{
    public function test_marzban_recovery_acquires_the_real_lock_before_recovering_a_stale_transaction(): void
    {
        $ssh = new InterruptedRecoveryFakeSshConnection;
        $recovery = new SshMarzbanHttpsInterruptedOperationRecovery(
            $this->privileged($ssh),
        );

        $recovery->recover();

        $command = $ssh->remoteCommand();

        self::assertStringContainsString(
            "lock_file='/var/lock/xdeploy-marzban-https-runtime.lock'",
            $command,
        );
        self::assertStringContainsString(
            'transaction_file="$marzban_path/.xdeploy-https-runtime-transaction"',
            $command,
        );
        self::assertStringContainsString(
            'cp -p "$backup_dir/.env" "$temporary_file"',
            $command,
        );
        self::assertStringContainsString(
            'rm -f "$transaction_file"',
            $command,
        );
        self::assertTrue(
            strpos($command, 'flock -n 9')
            < strpos($command, 'if [ ! -e "$transaction_file" ]'),
        );
        self::assertTrue($ssh->remoteCommandSensitive());
    }

    public function test_marzban_recovery_preserves_real_lock_contention_as_operation_in_progress(): void
    {
        $ssh = new InterruptedRecoveryFakeSshConnection(
            remoteExitCode: 75,
        );
        $recovery = new SshMarzbanHttpsInterruptedOperationRecovery(
            $this->privileged($ssh),
        );

        try {
            $recovery->recover();
            self::fail('Expected an interrupted operation recovery exception.');
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::OperationInProgress,
                $exception->failure,
            );
        }
    }

    public function test_n8n_recovery_acquires_the_real_lock_before_recovering_a_stale_transaction(): void
    {
        $ssh = new InterruptedRecoveryFakeSshConnection;
        $recovery = new SshN8nPublicEndpointInterruptedOperationRecovery(
            $this->privileged($ssh),
        );

        $recovery->recover();

        $command = $ssh->remoteCommand();

        self::assertStringContainsString(
            "lock_file='/var/lock/xdeploy-n8n-public-endpoint.lock'",
            $command,
        );
        self::assertStringContainsString(
            'transaction_file="$app_dir/.xdeploy-public-endpoint-transaction"',
            $command,
        );
        self::assertStringContainsString(
            'cp -p "$backup_dir/.env" "$temporary"',
            $command,
        );
        self::assertStringContainsString(
            'rm -f "$transaction_file"',
            $command,
        );
        self::assertTrue(
            strpos($command, 'flock -n 9')
            < strpos($command, 'if [ ! -e "$transaction_file" ]'),
        );
        self::assertTrue($ssh->remoteCommandSensitive());
    }

    public function test_n8n_recovery_preserves_real_lock_contention_as_operation_in_progress(): void
    {
        $ssh = new InterruptedRecoveryFakeSshConnection(
            remoteExitCode: 75,
        );
        $recovery = new SshN8nPublicEndpointInterruptedOperationRecovery(
            $this->privileged($ssh),
        );

        try {
            $recovery->recover();
            self::fail('Expected an interrupted operation recovery exception.');
        } catch (PublicEndpointOperationException $exception) {
            self::assertSame(
                PublicEndpointOperationFailure::OperationInProgress,
                $exception->failure,
            );
        }
    }

    private function privileged(
        SSHConnectionInterface $ssh,
    ): PrivilegedCommandExecutor {
        return new PrivilegedCommandExecutor(
            ssh: $ssh,
            preflight: new PrivilegedExecutionPreflight($ssh),
        );
    }
}

final class InterruptedRecoveryFakeSshConnection implements SSHConnectionInterface
{
    /**
     * @var list<array{command:string,sensitive:bool}>
     */
    private array $commands = [];

    public function __construct(
        private readonly int $remoteExitCode = 0,
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
        $this->commands[] = [
            'command' => $command,
            'sensitive' => $sensitive,
        ];

        if (trim($command) === 'id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        return new SSHResult(
            output: '',
            exitCode: $this->remoteExitCode,
        );
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void {}

    public function remoteCommand(): string
    {
        return $this->commands[1]['command'] ?? '';
    }

    public function remoteCommandSensitive(): bool
    {
        return $this->commands[1]['sensitive'] ?? false;
    }
}
