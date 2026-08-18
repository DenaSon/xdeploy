<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\N8n\PublicEndpoint;

use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\N8n\PublicEndpoint\SshN8nPublicEndpointInterruptedOperationRecovery;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class SshN8nPublicEndpointInterruptedOperationRecoveryTest extends TestCase
{
    public function test_interrupted_recovery_waits_for_n8n_readiness_before_clearing_transaction(): void
    {
        $ssh = new N8nInterruptedRecoveryFakeSshConnection(
            remoteResult: new SSHResult(
                output: implode("\n", [
                    'xdeploy_n8n_interrupted_recovery=1',
                    'interrupted=1',
                    'recovered=1',
                    'stage=completed',
                    'readiness_attempts=4',
                    'readiness_http_code=200',
                    'readiness_container_running=1',
                ]),
                exitCode: 0,
            ),
        );

        $recovery = $this->recovery($ssh);

        $recovery->recover();

        $command = $ssh->remoteCommand();

        self::assertStringContainsString(
            "'http://127.0.0.1:5678/healthz/readiness'",
            $command,
        );
        self::assertStringContainsString(
            '--connect-timeout 1',
            $command,
        );
        self::assertStringContainsString(
            '--max-time 1',
            $command,
        );
        self::assertStringContainsString(
            'wait_for_runtime_readiness() {',
            $command,
        );
        self::assertStringContainsString(
            'while [ "$attempt" -le 30 ]; do',
            $command,
        );
        self::assertStringContainsString(
            'if ! wait_for_runtime_readiness; then',
            $command,
        );
        self::assertTrue(
            strpos($command, 'if ! wait_for_runtime_readiness; then')
            < strpos($command, 'rm -f "$transaction_file"'),
        );
        self::assertStringNotContainsString(
            "'http://127.0.0.1:5678/'",
            $command,
        );
        self::assertTrue($ssh->remoteCommandSensitive());
    }

    public function test_failed_interrupted_recovery_logs_only_sanitized_structured_diagnostics(): void
    {
        Log::spy();

        $ssh = new N8nInterruptedRecoveryFakeSshConnection(
            remoteResult: new SSHResult(
                output: implode("\n", [
                    'xdeploy_n8n_interrupted_recovery=1',
                    'interrupted=1',
                    'recovered=0',
                    'stage=readiness',
                    'readiness_attempts=30',
                    'readiness_http_code=000',
                    'readiness_container_running=1',
                    'access_token=must-never-be-logged',
                    'refresh_token=must-never-be-logged',
                ]),
                exitCode: 74,
            ),
        );

        $recovery = $this->recovery($ssh);

        try {
            $recovery->recover();
            self::fail('Expected interrupted recovery to fail.');
        } catch (PublicEndpointOperationException $exception) {
            self::assertSame(
                PublicEndpointOperationFailure::Mutation,
                $exception->failure,
            );
            self::assertTrue($exception->recoveryAttempted());
            self::assertFalse($exception->recovered());
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'public_endpoint.n8n.interrupted_recovery_failed',
                Mockery::on(static function (array $context): bool {
                    return array_keys($context) === [
                        'stage',
                        'exit_code',
                        'interrupted',
                        'recovered',
                        'readiness_attempts',
                        'readiness_http_code',
                        'readiness_container_running',
                    ]
                        && $context['stage'] === 'readiness'
                        && $context['exit_code'] === 74
                        && $context['interrupted'] === true
                        && $context['recovered'] === false
                        && $context['readiness_attempts'] === 30
                        && $context['readiness_http_code'] === '000'
                        && $context['readiness_container_running'] === true;
                }),
            );
    }

    public function test_busy_interrupted_recovery_remains_operation_in_progress(): void
    {
        $ssh = new N8nInterruptedRecoveryFakeSshConnection(
            remoteResult: new SSHResult(
                output: implode("\n", [
                    'xdeploy_n8n_interrupted_recovery=1',
                    'interrupted=1',
                    'recovered=0',
                    'stage=busy',
                ]),
                exitCode: 75,
            ),
        );

        $recovery = $this->recovery($ssh);

        try {
            $recovery->recover();
            self::fail('Expected operation-in-progress failure.');
        } catch (PublicEndpointOperationException $exception) {
            self::assertSame(
                PublicEndpointOperationFailure::OperationInProgress,
                $exception->failure,
            );
        }
    }

    private function recovery(
        SSHConnectionInterface $ssh,
    ): SshN8nPublicEndpointInterruptedOperationRecovery {
        return new SshN8nPublicEndpointInterruptedOperationRecovery(
            new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight($ssh),
            ),
        );
    }
}

final class N8nInterruptedRecoveryFakeSshConnection implements SSHConnectionInterface
{
    /**
     * @var list<array{command:string,sensitive:bool}>
     */
    private array $commands = [];

    public function __construct(
        private readonly SSHResult $remoteResult,
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

        return $this->remoteResult;
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
