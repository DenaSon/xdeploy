<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Platform\Caddy;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Platform\Caddy\Configuration\CaddySiteConfigurationFactory;
use App\Infrastructure\Platform\Caddy\SshCaddySiteReader;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class SshCaddySiteReaderTest extends TestCase
{
    public function test_it_reads_only_the_xdeploy_managed_caddy_surface(): void
    {
        $site = CaddySite::reverseProxy(
            key: CaddySiteKey::from('marzban'),
            domain: 'panel.example.com',
            upstream: '127.0.0.1:8000',
        );

        $factory = new CaddySiteConfigurationFactory;
        $ssh = new CaddySiteReaderFakeSshConnection(
            siteConfiguration: $factory->make($site),
        );

        $reader = new SshCaddySiteReader(
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    ssh: $ssh,
                ),
            ),
            configurationFactory: $factory,
        );

        self::assertTrue($reader->environmentReady());
        self::assertTrue($reader->exists($site->key));
        self::assertTrue($reader->matches($site));

        self::assertStringContainsString(
            '# xDeploy: caddy-platform',
            implode("\n", $ssh->commands),
        );

        self::assertStringContainsString(
            '/etc/caddy/xdeploy/sites/marzban.caddy',
            implode("\n", $ssh->commands),
        );
    }
}

final class CaddySiteReaderFakeSshConnection implements SSHConnectionInterface
{
    /**
     * @var list<string>
     */
    public array $commands = [];

    public function __construct(
        private readonly string $siteConfiguration,
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

        if (str_contains($command, "managed_marker='# xDeploy: caddy-platform'")) {
            return new SSHResult(
                output: '',
                exitCode: 0,
            );
        }

        if (str_contains($command, 'cat "$site_file"')) {
            return new SSHResult(
                output: $this->siteConfiguration,
                exitCode: 0,
            );
        }

        if (str_contains($command, '[ -e "$site_file" ]')) {
            return new SSHResult(
                output: '',
                exitCode: 0,
            );
        }

        return new SSHResult(
            output: '',
            exitCode: 1,
        );
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void {}
}
