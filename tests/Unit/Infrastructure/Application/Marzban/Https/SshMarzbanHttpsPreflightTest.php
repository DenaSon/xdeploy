<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\Marzban\Https;

use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsLayoutState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\Marzban\Https\SshMarzbanHttpsPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class SshMarzbanHttpsPreflightTest extends TestCase
{
    public function test_dns_preflight_ignores_ipv4_mapped_ipv6_addresses(): void
    {
        $ssh = new MarzbanHttpsPreflightFakeSshConnection(
            dnsResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_dns_preflight=1
server_ipv4=91.107.140.21
a=91.107.140.21
aaaa=::ffff:91.107.140.21
aaaa=0:0:0:0:0:ffff:5b6b:8c15
OUTPUT,
                exitCode: 0,
            ),
        );

        $result = $this->preflight($ssh)->dns(
            domain: MarzbanDomain::from('panel.xdeploy.ir'),
            knownServerAddress: '91.107.140.21',
        );

        self::assertSame([], $result->resolvedIpv6Addresses);
        self::assertFalse($result->hasIncompatibleIpv6());
        self::assertTrue($result->ready());
        self::assertSame(SSHTimeout::NORMAL, $ssh->dnsTimeout);
        self::assertFalse($ssh->dnsSensitive);
        self::assertStringNotContainsString('sudo', $ssh->dnsCommand);
        self::assertStringContainsString('panel.xdeploy.ir', $ssh->dnsCommand);
    }

    public function test_dns_preflight_keeps_a_genuine_ipv6_as_incompatible(): void
    {
        $ssh = new MarzbanHttpsPreflightFakeSshConnection(
            dnsResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_dns_preflight=1
server_ipv4=91.107.140.21
a=91.107.140.21
aaaa=2001:db8::10
OUTPUT,
                exitCode: 0,
            ),
        );

        $result = $this->preflight($ssh)->dns(
            domain: MarzbanDomain::from('panel.xdeploy.ir'),
            knownServerAddress: '91.107.140.21',
        );

        self::assertSame(
            ['2001:db8::10'],
            $result->resolvedIpv6Addresses,
        );
        self::assertTrue($result->hasIncompatibleIpv6());
        self::assertFalse($result->ready());
    }

    public function test_server_preflight_only_inspects_the_base_marzban_compose_layout(): void
    {
        $ssh = new MarzbanHttpsPreflightFakeSshConnection(
            serverResult: new SSHResult(
                output: <<<'OUTPUT'
xdeploy_server_preflight=1
layout_state=supported
port_80_state=available
port_80_owner=none
port_443_state=conflict
port_443_owner=nginx
OUTPUT,
                exitCode: 0,
            ),
        );

        $result = $this->preflight($ssh)->server();

        self::assertSame(
            MarzbanHttpsLayoutState::Supported,
            $result->layoutState,
        );
        self::assertFalse($result->managedCaddyDetected);
        self::assertSame(
            MarzbanHttpsPortState::Available,
            $result->port80->state,
        );
        self::assertSame(
            MarzbanHttpsPortOwner::Nginx,
            $result->port443->owner,
        );
        self::assertFalse($result->ready());

        $commands = implode("\n", $ssh->commands);

        self::assertStringContainsString(
            '-f "$compose_file"',
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
            'xDeploy: marzban-https',
            $commands,
        );
    }

    private function preflight(
        SSHConnectionInterface $ssh,
    ): SshMarzbanHttpsPreflight {
        return new SshMarzbanHttpsPreflight(
            ssh: $ssh,
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    ssh: $ssh,
                ),
            ),
        );
    }
}

final class MarzbanHttpsPreflightFakeSshConnection implements SSHConnectionInterface
{
    /**
     * @var list<string>
     */
    public array $commands = [];

    public string $dnsCommand = '';

    public int $dnsTimeout = 0;

    public bool $dnsSensitive = false;

    public function __construct(
        private readonly ?SSHResult $dnsResult = null,
        private readonly ?SSHResult $serverResult = null,
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

        if (str_contains($command, 'xdeploy_dns_preflight=1')) {
            $this->dnsCommand = $command;
            $this->dnsTimeout = $timeout;
            $this->dnsSensitive = $sensitive;

            return $this->dnsResult ?? new SSHResult(
                output: '',
                exitCode: 1,
            );
        }

        if (str_contains($command, 'xdeploy_server_preflight=1')) {
            return $this->serverResult ?? new SSHResult(
                output: '',
                exitCode: 1,
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
