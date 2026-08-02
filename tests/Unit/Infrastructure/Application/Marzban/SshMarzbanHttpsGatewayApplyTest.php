<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
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
        $ssh = new ApplyFakeSshConnection(new SSHResult(
            output: <<<'OUTPUT'
xdeploy_https_apply=1
status=enabled
domain=panel.example.com
configuration_changed=1
panel_url=https://panel.example.com/dashboard/
OUTPUT,
            exitCode: 0,
        ));

        $result = $this->gateway($ssh)->enable(
            MarzbanDomain::from('panel.example.com'),
        );

        self::assertStringContainsString(
            <<<'SHELL'
caddy:2-alpine \
    caddy \
    validate \
SHELL,
            $ssh->command,
        );

        self::assertSame('panel.example.com', $result->domain);
        self::assertTrue($result->configurationChanged);
        self::assertTrue($ssh->sensitive);
    }

    public function test_it_reports_a_successful_compensation_explicitly(): void
    {
        $ssh = new ApplyFakeSshConnection(new SSHResult(
            output: <<<'OUTPUT'
xdeploy_https_apply=1
status=failed
stage=verification
configuration_restored=1
services_recovered=1
OUTPUT,
            exitCode: 73,
        ));

        try {
            $this->gateway($ssh)->enable(
                MarzbanDomain::from('panel.example.com'),
            );

            self::fail('Expected an apply exception.');
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::Verification,
                $exception->failure,
            );
            self::assertTrue($exception->recoveryAttempted());
            self::assertTrue($exception->recovered());
        }
    }

    public function test_candidate_failure_never_reports_a_recovery_attempt(): void
    {
        $ssh = new ApplyFakeSshConnection(new SSHResult(
            output: <<<'OUTPUT'
xdeploy_https_apply=1
status=failed
stage=candidate
configuration_restored=0
services_recovered=0
OUTPUT,
            exitCode: 71,
        ));

        try {
            $this->gateway($ssh)->enable(
                MarzbanDomain::from('panel.example.com'),
            );

            self::fail('Expected an apply exception.');
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::CandidateValidation,
                $exception->failure,
            );
            self::assertFalse($exception->recoveryAttempted());
        }
    }

    private function gateway(
        ApplyFakeSshConnection $ssh,
    ): SshMarzbanHttpsGateway {
        return new SshMarzbanHttpsGateway(
            ssh: $ssh,
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

    public function __construct(
        private readonly SSHResult $sshResult,
    ) {}

    public function connect(Server $server): bool
    {
        return true;
    }

    public function execute(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
    ): string {
        return $this->sshResult->output;
    }

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
        bool $sensitive = false,
    ): SSHResult {
        $this->command = $command;
        $this->timeout = $timeout;
        $this->sensitive = $sensitive;

        return $this->sshResult;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void {}
}
