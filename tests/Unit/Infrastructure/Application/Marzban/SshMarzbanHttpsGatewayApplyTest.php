<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanCaddyfileFactory;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanComposeOverrideFactory;
use App\Infrastructure\Application\Marzban\SshMarzbanHttpsGateway;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class SshMarzbanHttpsGatewayApplyTest extends TestCase
{
    public function test_it_returns_a_verified_apply_result(): void
    {
        $ssh = new ApplyFakeSshConnection(
            applyResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_https_apply=1
status=enabled
domain=panel.example.com
configuration_changed=1
panel_url=https://panel.example.com/dashboard/
OUTPUT,
                exitCode: 0,
            ),
        );

        $result = $this->gateway($ssh)->enable(
            MarzbanDomain::from(
                'panel.example.com',
            ),
        );

        self::assertSame(
            'panel.example.com',
            $result->domain,
        );

        self::assertSame(
            'https://panel.example.com/dashboard/',
            $result->panelUrl,
        );

        self::assertTrue(
            $result->configurationChanged,
        );

        self::assertTrue(
            $ssh->sensitive,
        );

        self::assertSame(
            SSHTimeout::APPLICATION_INSTALL,
            $ssh->timeout,
        );

        self::assertStringContainsString(
            <<<'SHELL'
caddy:2-alpine \
    caddy \
    validate \
SHELL,
            $ssh->command,
        );

        self::assertStringContainsString(
            'panel.example.com',
            $ssh->command,
        );

        self::assertSame(
            [
                'id -u',
                $ssh->command,
            ],
            $ssh->executedCommands(),
        );
    }

    public function test_it_reports_a_successful_compensation_explicitly(): void
    {
        $ssh = new ApplyFakeSshConnection(
            applyResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_https_apply=1
status=failed
stage=verification
configuration_restored=1
services_recovered=1
OUTPUT,
                exitCode: 73,
            ),
        );

        try {
            $this->gateway($ssh)->enable(
                MarzbanDomain::from(
                    'panel.example.com',
                ),
            );

            self::fail(
                'Expected a Marzban HTTPS apply exception.',
            );
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::Verification,
                $exception->failure,
            );

            self::assertTrue(
                $exception->recoveryAttempted(),
            );

            self::assertTrue(
                $exception->recovered(),
            );
        }

        self::assertTrue(
            $ssh->sensitive,
        );

        self::assertSame(
            SSHTimeout::APPLICATION_INSTALL,
            $ssh->timeout,
        );
    }

    public function test_candidate_failure_never_reports_a_recovery_attempt(): void
    {
        $ssh = new ApplyFakeSshConnection(
            applyResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_https_apply=1
status=failed
stage=candidate
configuration_restored=0
services_recovered=0
OUTPUT,
                exitCode: 71,
            ),
        );

        try {
            $this->gateway($ssh)->enable(
                MarzbanDomain::from(
                    'panel.example.com',
                ),
            );

            self::fail(
                'Expected a Marzban HTTPS apply exception.',
            );
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::CandidateValidation,
                $exception->failure,
            );

            self::assertFalse(
                $exception->recoveryAttempted(),
            );

            self::assertFalse(
                $exception->recovered(),
            );
        }

        self::assertTrue(
            $ssh->sensitive,
        );
    }

    private function gateway(
        SSHConnectionInterface $ssh,
    ): SshMarzbanHttpsGateway {
        return new SshMarzbanHttpsGateway(
            ssh: $ssh,
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    ssh: $ssh,
                ),
            ),
            composeOverrideFactory: new MarzbanComposeOverrideFactory,
            caddyfileFactory: new MarzbanCaddyfileFactory,
        );
    }
}

final class ApplyFakeSshConnection implements SSHConnectionInterface
{
    public bool $sensitive = false;

    public int $timeout = 0;

    public string $command = '';

    /**
     * @var list<array{
     *     command: string,
     *     timeout: int,
     *     sensitive: bool
     * }>
     */
    private array $executions = [];

    public function __construct(
        private readonly SSHResult $applyResult,
    ) {}

    public function connect(
        Server $server,
    ): bool {
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
        $this->executions[] = [
            'command' => $command,
            'timeout' => $timeout,
            'sensitive' => $sensitive,
        ];

        /*
         * Simulate a direct root SSH session for the privilege
         * preflight performed by PrivilegedCommandExecutor.
         */
        if (trim($command) === 'id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        /*
         * This test suite intentionally models root mode. Reaching
         * this command would indicate an unexpected preflight path.
         */
        if (trim($command) === 'sudo -n id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        $this->command = $command;
        $this->timeout = $timeout;
        $this->sensitive = $sensitive;

        return $this->applyResult;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void
    {
        //
    }

    /**
     * @return list<string>
     */
    public function executedCommands(): array
    {
        return array_map(
            static fn (array $execution): string => $execution['command'],
            $this->executions,
        );
    }
}
