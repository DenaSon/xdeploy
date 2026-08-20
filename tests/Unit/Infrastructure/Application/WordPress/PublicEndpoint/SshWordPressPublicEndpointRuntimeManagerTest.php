<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\WordPress\PublicEndpoint;

use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\WordPress\PublicEndpoint\SshWordPressPublicEndpointRuntimeManager;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class SshWordPressPublicEndpointRuntimeManagerTest extends TestCase
{
    public function test_inspect_reads_only_the_managed_public_url(): void
    {
        $ssh = new WordPressRuntimeManagerFakeSshConnection(
            remoteResult: new SSHResult(
                output: 'public_url=https://blog.example.com',
                exitCode: 0,
            ),
        );

        $configuration = $this->manager($ssh)->inspect();

        self::assertSame(
            'https://blog.example.com',
            $configuration->publicUrl,
        );
        self::assertStringContainsString(
            "read_value 'XDEPLOY_WORDPRESS_PUBLIC_URL'",
            $ssh->remoteCommand(),
        );
        self::assertStringNotContainsString(
            'WORDPRESS_DB_PASSWORD',
            $ssh->remoteCommand(),
        );
    }

    public function test_enable_prepares_a_transactional_wordpress_only_recreate(): void
    {
        $ssh = new WordPressRuntimeManagerFakeSshConnection(
            remoteResult: new SSHResult(
                output: implode("\n", [
                    'xdeploy_wordpress_endpoint_runtime=1',
                    'status=prepared',
                    'backup_token=runtime.ABC123',
                    'configuration_changed=1',
                ]),
                exitCode: 0,
            ),
        );

        $mutation = $this->manager($ssh)->prepareEnabled(
            PublicEndpointDomain::from('blog.example.com'),
        );

        self::assertTrue($mutation->configurationChanged);
        self::assertSame('runtime.ABC123', $mutation->backupToken);

        $command = $ssh->remoteCommand();

        self::assertStringContainsString(
            'XDEPLOY_WORDPRESS_PUBLIC_URL=https://',
            $command,
        );
        self::assertStringContainsString(
            "domain='blog.example.com'",
            $command,
        );
        self::assertStringContainsString(
            'compose up -d --no-deps --force-recreate wordpress',
            $command,
        );
        self::assertStringContainsString(
            "grep -Fq 'WORDPRESS_CONFIG_EXTRA:'",
            $command,
        );
        self::assertStringContainsString(
            '--header "Host: $request_host"',
            $command,
        );
        self::assertStringContainsString(
            '--header "X-Forwarded-Proto: $forwarded_proto"',
            $command,
        );
        self::assertStringContainsString(
            'while [ "$attempt" -le 30 ]; do',
            $command,
        );
        self::assertStringNotContainsString(
            'compose up -d --force-recreate',
            $command,
        );
        self::assertTrue($ssh->remoteCommandSensitive());
    }

    public function test_recovered_verification_failure_logs_sanitized_diagnostics(): void
    {
        Log::spy();

        $ssh = new WordPressRuntimeManagerFakeSshConnection(
            remoteResult: new SSHResult(
                output: implode("\n", [
                    'xdeploy_wordpress_endpoint_runtime=1',
                    'status=failed',
                    'stage=verification',
                    'configuration_restored=1',
                    'services_recovered=1',
                    'verification_attempts=30',
                    'verification_http_code=302',
                    'verification_container_running=1',
                    'verification_container_health=unhealthy',
                    'recovery_attempted=1',
                    'recovery_readiness_attempts=4',
                    'recovery_readiness_http_code=200',
                    'recovery_container_running=1',
                    'recovery_container_health=healthy',
                    'database_password=must-never-be-logged',
                ]),
                exitCode: 73,
            ),
        );

        try {
            $this->manager($ssh)->prepareEnabled(
                PublicEndpointDomain::from('blog.example.com'),
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
                'public_endpoint.wordpress.runtime_verification_failed',
                Mockery::on(function (array $context): bool {
                    self::assertSame('verification', $context['stage']);
                    self::assertSame(73, $context['exit_code']);
                    self::assertSame(30, $context['verification_attempts']);
                    self::assertSame('302', $context['verification_http_code']);
                    self::assertTrue($context['verification_container_running']);
                    self::assertSame('unhealthy', $context['verification_container_health']);
                    self::assertTrue($context['recovery_attempted']);
                    self::assertTrue($context['configuration_restored']);
                    self::assertTrue($context['services_recovered']);
                    self::assertSame(4, $context['recovery_readiness_attempts']);
                    self::assertSame('200', $context['recovery_readiness_http_code']);
                    self::assertTrue($context['recovery_container_running']);
                    self::assertSame('healthy', $context['recovery_container_health']);
                    self::assertArrayNotHasKey('database_password', $context);

                    return true;
                }),
            );
    }

    private function manager(
        SSHConnectionInterface $ssh,
    ): SshWordPressPublicEndpointRuntimeManager {
        return new SshWordPressPublicEndpointRuntimeManager(
            new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight($ssh),
            ),
        );
    }
}

final class WordPressRuntimeManagerFakeSshConnection implements SSHConnectionInterface
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

    public function remoteCommandSensitive(): bool
    {
        return $this->commands[1]['sensitive'] ?? false;
    }
}
