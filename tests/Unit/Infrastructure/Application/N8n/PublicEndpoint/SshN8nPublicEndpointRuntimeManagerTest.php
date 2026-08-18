<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\N8n\PublicEndpoint;

use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\N8n\PublicEndpoint\SshN8nPublicEndpointRuntimeManager;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class SshN8nPublicEndpointRuntimeManagerTest extends TestCase
{
    public function test_enable_uses_a_bounded_readiness_waiter_for_primary_and_recovery_checks(): void
    {
        $ssh = new N8nRuntimeManagerFakeSshConnection(
            remoteResult: new SSHResult(
                output: implode("\n", [
                    'xdeploy_n8n_endpoint_runtime=1',
                    'status=prepared',
                    'backup_token=runtime.ABC123',
                    'configuration_changed=1',
                ]),
                exitCode: 0,
            ),
        );

        $manager = $this->manager($ssh);

        $mutation = $manager->prepareEnabled(
            PublicEndpointDomain::from('app.example.com'),
        );

        self::assertTrue($mutation->configurationChanged);
        self::assertSame('runtime.ABC123', $mutation->backupToken);

        $command = $ssh->remoteCommand();

        self::assertStringContainsString(
            "'http://127.0.0.1:5678/healthz/readiness'",
            $command,
        );
        self::assertStringContainsString(
            '--connect-timeout 1 --max-time 1',
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
        self::assertGreaterThanOrEqual(
            3,
            substr_count($command, 'wait_for_runtime_readiness'),
        );
        self::assertStringNotContainsString(
            'recovery="$(compensate)"',
            $command,
        );
        self::assertStringNotContainsString(
            "'http://127.0.0.1:5678/' 2>/dev/null",
            $command,
        );
    }

    public function test_recovered_verification_failure_logs_only_structured_sanitized_diagnostics(): void
    {
        Log::spy();

        $ssh = new N8nRuntimeManagerFakeSshConnection(
            remoteResult: new SSHResult(
                output: implode("\n", [
                    'xdeploy_n8n_endpoint_runtime=1',
                    'status=failed',
                    'stage=verification',
                    'configuration_restored=1',
                    'services_recovered=1',
                    'verification_attempts=30',
                    'verification_http_code=000',
                    'verification_container_running=1',
                    'recovery_attempted=1',
                    'recovery_readiness_attempts=4',
                    'recovery_readiness_http_code=200',
                    'recovery_container_running=1',
                    'access_token=must-never-be-logged',
                ]),
                exitCode: 73,
            ),
        );

        $manager = $this->manager($ssh);

        try {
            $manager->prepareEnabled(
                PublicEndpointDomain::from('app.example.com'),
            );
            self::fail('Expected a public endpoint verification exception.');
        } catch (PublicEndpointOperationException $exception) {
            self::assertSame(
                PublicEndpointOperationFailure::Verification,
                $exception->failure,
            );
            self::assertTrue($exception->recoveryAttempted());
            self::assertTrue($exception->recovered());
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'public_endpoint.n8n.runtime_verification_failed',
                Mockery::on(function (array $context): bool {
                    self::assertSame('verification', $context['stage']);
                    self::assertSame(73, $context['exit_code']);
                    self::assertSame(30, $context['verification_attempts']);
                    self::assertSame('000', $context['verification_http_code']);
                    self::assertTrue($context['verification_container_running']);
                    self::assertTrue($context['recovery_attempted']);
                    self::assertTrue($context['configuration_restored']);
                    self::assertTrue($context['services_recovered']);
                    self::assertSame(4, $context['recovery_readiness_attempts']);
                    self::assertSame('200', $context['recovery_readiness_http_code']);
                    self::assertTrue($context['recovery_container_running']);
                    self::assertArrayNotHasKey('access_token', $context);

                    return true;
                }),
            );
    }

    public function test_failed_recovery_preserves_recovery_attempt_and_logs_an_error(): void
    {
        Log::spy();

        $ssh = new N8nRuntimeManagerFakeSshConnection(
            remoteResult: new SSHResult(
                output: implode("\n", [
                    'xdeploy_n8n_endpoint_runtime=1',
                    'status=failed',
                    'stage=mutation',
                    'configuration_restored=0',
                    'services_recovered=0',
                    'verification_attempts=0',
                    'verification_http_code=',
                    'verification_container_running=0',
                    'recovery_attempted=1',
                    'recovery_readiness_attempts=30',
                    'recovery_readiness_http_code=000',
                    'recovery_container_running=0',
                ]),
                exitCode: 72,
            ),
        );

        $manager = $this->manager($ssh);

        try {
            $manager->prepareEnabled(
                PublicEndpointDomain::from('app.example.com'),
            );
            self::fail('Expected a public endpoint mutation exception.');
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
                'public_endpoint.n8n.runtime_verification_failed',
                Mockery::on(fn (array $context): bool =>
                    $context['stage'] === 'mutation'
                    && $context['exit_code'] === 72
                    && $context['recovery_attempted'] === true
                    && $context['configuration_restored'] === false
                    && $context['services_recovered'] === false
                    && $context['recovery_readiness_attempts'] === 30
                    && $context['recovery_readiness_http_code'] === '000'
                    && $context['recovery_container_running'] === false
                ),
            );
    }

    private function manager(
        SSHConnectionInterface $ssh,
    ): SshN8nPublicEndpointRuntimeManager {
        return new SshN8nPublicEndpointRuntimeManager(
            new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight($ssh),
            ),
        );
    }
}

final class N8nRuntimeManagerFakeSshConnection implements SSHConnectionInterface
{
    /** @var list<array{command:string,sensitive:bool}> */
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
}
