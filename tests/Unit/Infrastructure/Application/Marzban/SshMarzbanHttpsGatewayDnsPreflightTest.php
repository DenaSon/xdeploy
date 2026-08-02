<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanCaddyfileFactory;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanComposeOverrideFactory;
use App\Infrastructure\Application\Marzban\SshMarzbanHttpsGateway;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class SshMarzbanHttpsGatewayDnsPreflightTest extends TestCase
{
    public function test_it_ignores_ipv4_mapped_ipv6_addresses(): void
    {
        $ssh = new DnsPreflightFakeSshConnection(new SSHResult(
            output: <<<'OUTPUT'
xdeploy_dns_preflight=1
server_ipv4=91.107.140.21
a=91.107.140.21
aaaa=::ffff:91.107.140.21
aaaa=0:0:0:0:0:ffff:5b6b:8c15
OUTPUT,
            exitCode: 0,
        ));

        $result = $this->gateway($ssh)->preflightDns(
            domain: MarzbanDomain::from('panel.xdeploy.ir'),
            knownServerAddress: '91.107.140.21',
        );

        self::assertSame([], $result->resolvedIpv6Addresses);
        self::assertFalse($result->hasIncompatibleIpv6());
        self::assertTrue($result->ready());
    }

    public function test_it_keeps_a_genuine_ipv6_as_incompatible(): void
    {
        $ssh = new DnsPreflightFakeSshConnection(new SSHResult(
            output: <<<'OUTPUT'
xdeploy_dns_preflight=1
server_ipv4=91.107.140.21
a=91.107.140.21
aaaa=2001:db8::10
OUTPUT,
            exitCode: 0,
        ));

        $result = $this->gateway($ssh)->preflightDns(
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

    private function gateway(
        DnsPreflightFakeSshConnection $ssh,
    ): SshMarzbanHttpsGateway {
        return new SshMarzbanHttpsGateway(
            ssh: $ssh,
            composeOverrideFactory: new MarzbanComposeOverrideFactory,
            caddyfileFactory: new MarzbanCaddyfileFactory,
        );
    }
}

final class DnsPreflightFakeSshConnection implements SSHConnectionInterface
{
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
        return $this->result->output;
    }

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
        bool $sensitive = false,
    ): SSHResult {
        return $this->result;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void {}
}
