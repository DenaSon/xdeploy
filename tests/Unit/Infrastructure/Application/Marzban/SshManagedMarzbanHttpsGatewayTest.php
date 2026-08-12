<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteManagerInterface;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteReaderInterface;
use App\Domain\Platform\Caddy\Sites\DTOs\CaddySiteMutationResult;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanCaddyfileFactory;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanComposeOverrideFactory;
use App\Infrastructure\Application\Marzban\Https\SshMarzbanHttpsRuntimeManager;
use App\Infrastructure\Application\Marzban\SshManagedMarzbanHttpsGateway;
use App\Infrastructure\Application\Marzban\SshMarzbanHttpsGateway;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class SshManagedMarzbanHttpsGatewayTest extends TestCase
{
    public function test_it_configures_caddy_as_a_consumer_and_moves_marzban_to_loopback_runtime(): void
    {
        $ssh = new ManagedGatewayFakeSshConnection;
        $reader = new ManagedGatewayFakeCaddySiteReader;
        $sites = new ManagedGatewayFakeCaddySiteManager;

        $result = $this->gateway(
            ssh: $ssh,
            reader: $reader,
            sites: $sites,
        )->enable(
            MarzbanDomain::from('panel.example.com'),
        );

        self::assertSame('panel.example.com', $result->domain);
        self::assertSame(
            'https://panel.example.com/dashboard/',
            $result->panelUrl,
        );
        self::assertTrue($result->configurationChanged);

        self::assertNotNull($sites->upserted);
        self::assertSame('marzban', $sites->upserted->key->value);
        self::assertSame('panel.example.com', $sites->upserted->domain);
        self::assertSame('127.0.0.1:8000', $sites->upserted->upstream);
        self::assertNull($sites->removed);

        $commands = implode("\n", $ssh->commands);

        self::assertStringContainsString(
            "-v host='127.0.0.1'",
            $commands,
        );
        self::assertStringContainsString(
            'if (key == "UVICORN_UDS")',
            $commands,
        );
        self::assertStringContainsString(
            'UVICORN_SSL_CERTFILE',
            $commands,
        );
        self::assertStringContainsString(
            'UVICORN_SSL_KEYFILE',
            $commands,
        );
        self::assertStringContainsString(
            'XRAY_SUBSCRIPTION_URL_PREFIX=https://$domain',
            $commands,
        );
        self::assertStringContainsString(
            '-f "$compose_file"',
            $commands,
        );
        self::assertStringNotContainsString(
            '-f "$legacy_overlay"',
            $commands,
        );
        self::assertStringNotContainsString(
            '--remove-orphans',
            $commands,
        );
    }

    public function test_it_reports_the_new_host_caddy_runtime_as_enabled(): void
    {
        $ssh = new ManagedGatewayFakeSshConnection;
        $reader = new ManagedGatewayFakeCaddySiteReader;
        $reader->siteExists = true;
        $sites = new ManagedGatewayFakeCaddySiteManager;

        $info = $this->gateway(
            ssh: $ssh,
            reader: $reader,
            sites: $sites,
        )->inspect();

        self::assertSame(MarzbanHttpsState::Enabled, $info->state);
        self::assertSame('panel.example.com', $info->domain);
        self::assertNotNull($reader->matchedSite);
        self::assertSame(
            '127.0.0.1:8000',
            $reader->matchedSite->upstream,
        );
    }

    public function test_it_treats_ports_owned_by_managed_host_caddy_as_available_for_xdeploy(): void
    {
        $ssh = new ManagedGatewayFakeSshConnection;
        $reader = new ManagedGatewayFakeCaddySiteReader;
        $sites = new ManagedGatewayFakeCaddySiteManager;

        $preflight = $this->gateway(
            ssh: $ssh,
            reader: $reader,
            sites: $sites,
        )->preflightServer();

        self::assertTrue($preflight->managedCaddyDetected);
        self::assertTrue($preflight->ready());
        self::assertSame(
            MarzbanHttpsPortState::Managed,
            $preflight->port80->state,
        );
        self::assertSame(
            MarzbanHttpsPortOwner::XDeployCaddy,
            $preflight->port80->owner,
        );
        self::assertSame(
            MarzbanHttpsPortState::Managed,
            $preflight->port443->state,
        );
    }

    public function test_it_removes_the_caddy_site_when_marzban_runtime_preparation_fails(): void
    {
        $ssh = new ManagedGatewayFakeSshConnection(
            failPrepare: true,
        );
        $reader = new ManagedGatewayFakeCaddySiteReader;
        $sites = new ManagedGatewayFakeCaddySiteManager;

        try {
            $this->gateway(
                ssh: $ssh,
                reader: $reader,
                sites: $sites,
            )->enable(
                MarzbanDomain::from('panel.example.com'),
            );

            self::fail(
                'Expected a Marzban HTTPS apply exception.',
            );
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::CandidateValidation,
                $exception->failure,
            );
        }

        self::assertSame('marzban', $sites->removed?->value);
    }

    private function gateway(
        SSHConnectionInterface $ssh,
        CaddySiteReaderInterface $reader,
        CaddySiteManagerInterface $sites,
    ): SshManagedMarzbanHttpsGateway {
        $privileged = new PrivilegedCommandExecutor(
            ssh: $ssh,
            preflight: new PrivilegedExecutionPreflight(
                ssh: $ssh,
            ),
        );

        return new SshManagedMarzbanHttpsGateway(
            ssh: $ssh,
            legacyGateway: new SshMarzbanHttpsGateway(
                ssh: $ssh,
                privileged: $privileged,
                composeOverrideFactory: new MarzbanComposeOverrideFactory,
                caddyfileFactory: new MarzbanCaddyfileFactory,
            ),
            runtime: new SshMarzbanHttpsRuntimeManager(
                ssh: $ssh,
                privileged: $privileged,
            ),
            caddySites: $reader,
            caddySiteManager: $sites,
        );
    }
}

final class ManagedGatewayFakeCaddySiteReader implements CaddySiteReaderInterface
{
    public bool $environmentReady = true;

    public bool $siteExists = false;

    public bool $matchesSite = true;

    public ?CaddySite $matchedSite = null;

    public function environmentReady(): bool
    {
        return $this->environmentReady;
    }

    public function exists(CaddySiteKey $key): bool
    {
        return $this->siteExists;
    }

    public function matches(CaddySite $site): bool
    {
        $this->matchedSite = $site;

        return $this->matchesSite;
    }
}

final class ManagedGatewayFakeCaddySiteManager implements CaddySiteManagerInterface
{
    public ?CaddySite $upserted = null;

    public ?CaddySiteKey $removed = null;

    public function upsert(
        CaddySite $site,
    ): CaddySiteMutationResult {
        $this->upserted = $site;

        return new CaddySiteMutationResult(
            key: $site->key,
            changed: true,
        );
    }

    public function remove(
        CaddySiteKey $key,
    ): CaddySiteMutationResult {
        $this->removed = $key;

        return new CaddySiteMutationResult(
            key: $key,
            changed: true,
        );
    }
}

final class ManagedGatewayFakeSshConnection implements SSHConnectionInterface
{
    /**
     * @var list<string>
     */
    public array $commands = [];

    public function __construct(
        private readonly bool $failPrepare = false,
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
        $this->commands[] = $command;

        if (trim($command) === 'id -u') {
            return new SSHResult(
                output: '0',
                exitCode: 0,
            );
        }

        if (str_contains($command, "printf 'host=%s")) {
            return new SSHResult(
                output: <<<'OUTPUT'
host=127.0.0.1
port=8000
uds=
cert=
key=
subscription_url=https://panel.example.com
OUTPUT,
                exitCode: 0,
            );
        }

        if (str_contains($command, "printf 'xdeploy_server_preflight=1")) {
            return new SSHResult(
                output: <<<'OUTPUT'
xdeploy_server_preflight=1
layout_state=supported
managed_caddy=0
port_80_state=conflict
port_80_owner=caddy
port_443_state=conflict
port_443_owner=caddy
OUTPUT,
                exitCode: 0,
            );
        }

        if (str_contains($command, 'xdeploy_marzban_runtime=1')) {
            if ($this->failPrepare) {
                return new SSHResult(
                    output: <<<'OUTPUT'
xdeploy_marzban_runtime=1
status=failed
stage=candidate
configuration_restored=0
services_recovered=0
OUTPUT,
                    exitCode: 71,
                );
            }

            return new SSHResult(
                output: <<<'OUTPUT'
xdeploy_marzban_runtime=1
status=prepared
backup_token=runtime.ABC123
configuration_changed=1
OUTPUT,
                exitCode: 0,
            );
        }

        if (str_contains($command, 'https://$domain/dashboard/')) {
            return new SSHResult(
                output: '',
                exitCode: 0,
            );
        }

        if (str_contains($command, '.xdeploy-https-runtime-transaction')) {
            return new SSHResult(
                output: '',
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
