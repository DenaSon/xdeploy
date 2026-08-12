<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Platform\Caddy;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Enums\CaddySiteMutationFailure;
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

final class SshCaddySiteManagerTest extends TestCase
{
    public function test_it_applies_a_site_with_validation_atomic_mutation_reload_and_recovery_guards(): void
    {
        $ssh = new CaddySiteManagerFakeSshConnection(
            mutationResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_mutation=1
status=applied
changed=1
OUTPUT,
                exitCode: 0,
            ),
        );

        $site = CaddySite::reverseProxy(
            key: CaddySiteKey::from('marzban'),
            domain: 'panel.example.com',
            upstream: '127.0.0.1:8000',
        );

        $result = $this->manager($ssh)->upsert($site);

        self::assertTrue(
            $result->changed,
        );

        self::assertSame(
            'marzban',
            $result->key->value,
        );

        self::assertTrue(
            $ssh->sensitive,
        );

        self::assertSame(
            SSHTimeout::NORMAL,
            $ssh->timeout,
        );

        self::assertStringContainsString(
            "managed_marker='# xDeploy: caddy-platform'",
            $ssh->command,
        );

        self::assertStringContainsString(
            'sites_dir="$managed_root/sites"',
            $ssh->command,
        );

        self::assertStringContainsString(
            'caddy validate',
            $ssh->command,
        );

        self::assertStringContainsString(
            'systemctl reload caddy',
            $ssh->command,
        );

        self::assertStringContainsString(
            'atomic_install',
            $ssh->command,
        );

        self::assertStringContainsString(
            'compensate()',
            $ssh->command,
        );

        $payload = base64_encode(
            (new CaddySiteConfigurationFactory)->make($site),
        );

        self::assertStringContainsString(
            'site_payload='.escapeshellarg($payload),
            $ssh->command,
        );

        $decodedPayload = base64_decode(
            $payload,
            true,
        );

        self::assertIsString(
            $decodedPayload,
        );

        self::assertStringContainsString(
            'panel.example.com',
            $decodedPayload,
        );

        self::assertStringContainsString(
            '127.0.0.1:8000',
            $decodedPayload,
        );

        self::assertSame(
            [
                'id -u',
                $ssh->command,
            ],
            $ssh->executedCommands(),
        );
    }

    public function test_it_returns_unchanged_without_a_false_mutation(): void
    {
        $ssh = new CaddySiteManagerFakeSshConnection(
            mutationResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_mutation=1
status=unchanged
changed=0
OUTPUT,
                exitCode: 0,
            ),
        );

        $result = $this->manager($ssh)->upsert(
            CaddySite::reverseProxy(
                key: CaddySiteKey::from('n8n'),
                domain: 'automation.example.com',
                upstream: '127.0.0.1:5678',
            ),
        );

        self::assertFalse(
            $result->changed,
        );
    }

    public function test_it_removes_a_managed_site(): void
    {
        $ssh = new CaddySiteManagerFakeSshConnection(
            mutationResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_mutation=1
status=removed
changed=1
OUTPUT,
                exitCode: 0,
            ),
        );

        $result = $this->manager($ssh)->remove(
            CaddySiteKey::from('marzban'),
        );

        self::assertTrue(
            $result->changed,
        );

        self::assertStringContainsString(
            'action='.escapeshellarg('remove'),
            $ssh->command,
        );

        self::assertStringContainsString(
            'site_key='.escapeshellarg('marzban'),
            $ssh->command,
        );
    }

    public function test_it_reports_candidate_validation_failure_without_recovery(): void
    {
        $ssh = new CaddySiteManagerFakeSshConnection(
            mutationResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_mutation=1
status=failed
stage=candidate
configuration_restored=0
service_recovered=0
OUTPUT,
                exitCode: 71,
            ),
        );

        try {
            $this->manager($ssh)->upsert(
                CaddySite::reverseProxy(
                    key: CaddySiteKey::from('n8n'),
                    domain: 'automation.example.com',
                    upstream: '127.0.0.1:5678',
                ),
            );

            self::fail(
                'Expected a Caddy site mutation exception.',
            );
        } catch (CaddySiteMutationException $exception) {
            self::assertSame(
                CaddySiteMutationFailure::CandidateValidation,
                $exception->failure,
            );

            self::assertFalse(
                $exception->recoveryAttempted(),
            );

            self::assertFalse(
                $exception->recovered(),
            );
        }
    }

    public function test_it_reports_a_reload_failure_with_successful_recovery(): void
    {
        $ssh = new CaddySiteManagerFakeSshConnection(
            mutationResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_mutation=1
status=failed
stage=reload
configuration_restored=1
service_recovered=1
OUTPUT,
                exitCode: 73,
            ),
        );

        try {
            $this->manager($ssh)->upsert(
                CaddySite::reverseProxy(
                    key: CaddySiteKey::from('marzban'),
                    domain: 'panel.example.com',
                    upstream: '127.0.0.1:8000',
                ),
            );

            self::fail(
                'Expected a Caddy site mutation exception.',
            );
        } catch (CaddySiteMutationException $exception) {
            self::assertSame(
                CaddySiteMutationFailure::Reload,
                $exception->failure,
            );

            self::assertTrue(
                $exception->recoveryAttempted(),
            );

            self::assertTrue(
                $exception->recovered(),
            );
        }
    }

    public function test_it_reports_recovery_failure_explicitly(): void
    {
        $ssh = new CaddySiteManagerFakeSshConnection(
            mutationResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_caddy_site_mutation=1
status=failed
stage=reload
configuration_restored=1
service_recovered=0
OUTPUT,
                exitCode: 74,
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
                CaddySiteMutationFailure::Recovery,
                $exception->failure,
            );

            self::assertTrue(
                $exception->recoveryAttempted(),
            );

            self::assertFalse(
                $exception->recovered(),
            );

            self::assertTrue(
                $exception->configurationRestored(),
            );

            self::assertFalse(
                $exception->serviceRecovered(),
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

final class CaddySiteManagerFakeSshConnection implements SSHConnectionInterface
{
    public bool $sensitive = false;

    public int $timeout = 0;

    public string $command = '';

    /**
     * @var list<string>
     */
    private array $executions = [];

    public function __construct(
        private readonly SSHResult $mutationResult,
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
        $this->executions[] = $command;

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

        return $this->mutationResult;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void {}

    /**
     * @return list<string>
     */
    public function executedCommands(): array
    {
        return $this->executions;
    }
}
