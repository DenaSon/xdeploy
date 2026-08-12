<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Platform\Caddy;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Enums\CaddySiteMutationFailure;
use App\Domain\Platform\Caddy\Sites\Exceptions\CaddySiteInspectionException;
use App\Domain\Platform\Caddy\Sites\Exceptions\CaddySiteMutationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Platform\Caddy\Configuration\CaddySiteConfigurationFactory;
use App\Infrastructure\Platform\Caddy\SshCaddySiteManager;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class SshCaddySiteManagerSafetyTest extends TestCase
{
    public function test_it_inspects_a_managed_site_without_reading_configuration_into_php(): void
    {
        $ssh = new CaddySiteSafetyFakeSshConnection(
            result: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_inspection=1
status=managed
OUTPUT,
                exitCode: 0,
            ),
        );

        $info = $this->manager($ssh)->inspect(
            CaddySiteKey::from('marzban'),
        );

        self::assertTrue($info->isManaged());
        self::assertSame('marzban', $info->key->value);
        self::assertFalse($ssh->sensitive);
        self::assertSame(SSHTimeout::QUICK, $ssh->timeout);
        self::assertStringContainsString(
            'head -n 1 "$site_file"',
            $ssh->command,
        );
    }

    public function test_it_reports_a_missing_site(): void
    {
        $ssh = new CaddySiteSafetyFakeSshConnection(
            result: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_inspection=1
status=missing
OUTPUT,
                exitCode: 0,
            ),
        );

        $info = $this->manager($ssh)->inspect(
            CaddySiteKey::from('n8n'),
        );

        self::assertTrue($info->isMissing());
    }

    public function test_it_reports_an_ownership_conflict_without_mutation(): void
    {
        $ssh = new CaddySiteSafetyFakeSshConnection(
            result: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_inspection=1
status=conflict
OUTPUT,
                exitCode: 0,
            ),
        );

        $info = $this->manager($ssh)->inspect(
            CaddySiteKey::from('n8n'),
        );

        self::assertTrue($info->hasConflict());
    }

    public function test_invalid_inspection_response_fails_closed(): void
    {
        $ssh = new CaddySiteSafetyFakeSshConnection(
            result: new SSHResult(
                output: 'unexpected',
                exitCode: 0,
            ),
        );

        $this->expectException(
            CaddySiteInspectionException::class,
        );

        $this->manager($ssh)->inspect(
            CaddySiteKey::from('marzban'),
        );
    }

    public function test_mutation_command_rejects_unowned_files_and_preserves_recovery_evidence(): void
    {
        $ssh = new CaddySiteSafetyFakeSshConnection(
            result: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_mutation=1
status=applied
changed=1
OUTPUT,
                exitCode: 0,
            ),
        );

        $this->manager($ssh)->upsert(
            CaddySite::reverseProxy(
                key: CaddySiteKey::from('n8n'),
                domain: 'automation.example.com',
                upstream: '127.0.0.1:5678',
            ),
        );

        self::assertStringContainsString(
            'current_marker="# xDeploy: caddy-site:$current_key"',
            $ssh->command,
        );

        self::assertStringContainsString(
            'grep -Fxq "$current_marker"',
            $ssh->command,
        );

        self::assertStringContainsString(
            'preserve_candidate=1',
            $ssh->command,
        );

        self::assertStringContainsString(
            '[ "$preserve_candidate" -eq 0 ]',
            $ssh->command,
        );

        self::assertStringContainsString(
            '[ "$actual_root" != "$expected_root" ]',
            $ssh->command,
        );

        self::assertStringContainsString(
            '--config "$caddyfile"',
            $ssh->command,
        );
    }

    public function test_configuration_conflict_has_a_distinct_failure(): void
    {
        $ssh = new CaddySiteSafetyFakeSshConnection(
            result: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_mutation=1
status=failed
stage=conflict
configuration_restored=0
service_recovered=0
OUTPUT,
                exitCode: 76,
            ),
        );

        try {
            $this->manager($ssh)->remove(
                CaddySiteKey::from('marzban'),
            );

            self::fail(
                'Expected a Caddy site mutation exception.',
            );
        } catch (CaddySiteMutationException $exception) {
            self::assertSame(
                CaddySiteMutationFailure::Conflict,
                $exception->failure,
            );

            self::assertFalse(
                $exception->recoveryAttempted(),
            );
        }
    }

    private function manager(
        SSHConnectionInterface $ssh,
    ): SshCaddySiteManager {
        return new SshCaddySiteManager(
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    ssh: $ssh,
                ),
            ),
            configurationFactory: new CaddySiteConfigurationFactory,
        );
    }
}

final class CaddySiteSafetyFakeSshConnection implements SSHConnectionInterface
{
    public bool $sensitive = false;

    public int $timeout = 0;

    public string $command = '';

    public function __construct(
        private readonly SSHResult $result,
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
        if (trim($command) === 'id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        if (trim($command) === 'sudo -n id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        $this->command = $command;
        $this->timeout = $timeout;
        $this->sensitive = $sensitive;

        return $this->result;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void {}
}
