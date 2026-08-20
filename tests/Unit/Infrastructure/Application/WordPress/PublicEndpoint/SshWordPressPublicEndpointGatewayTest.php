<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\WordPress\PublicEndpoint;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteManagerInterface;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteReaderInterface;
use App\Domain\Platform\Caddy\Sites\DTOs\CaddySiteMutationResult;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\WordPress\PublicEndpoint\SshWordPressPublicEndpointGateway;
use App\Infrastructure\Application\WordPress\PublicEndpoint\SshWordPressPublicEndpointRuntimeManager;
use App\Infrastructure\PublicEndpoint\SshPublicEndpointDnsPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use Tests\TestCase;

final class SshWordPressPublicEndpointGatewayTest extends TestCase
{
    public function test_server_preflight_accepts_the_managed_caddy_ports_and_wordpress_layout(): void
    {
        $ssh = new WordPressGatewayFakeSshConnection([
            new SSHResult(
                output: implode("\n", [
                    'xdeploy_wordpress_server_preflight=1',
                    'layout_state=supported',
                    'port_80_state=conflict',
                    'port_80_owner=caddy',
                    'port_443_state=conflict',
                    'port_443_owner=caddy',
                ]),
                exitCode: 0,
            ),
        ]);

        $caddySites = $this->createMock(
            CaddySiteReaderInterface::class,
        );
        $caddySites->expects(self::once())
            ->method('environmentReady')
            ->willReturn(true);

        $result = $this->gateway($ssh, $caddySites)->preflightServer();

        self::assertTrue($result->ready);
        self::assertTrue($result->layoutSupported);
        self::assertTrue($result->managedCaddyDetected);
        self::assertFalse($result->hasPortConflict);
        self::assertSame('managed', $result->ports[80]['state']);
        self::assertSame('xdeploy_caddy', $result->ports[443]['owner']);

        $command = $ssh->remoteCommands()[0] ?? '';

        self::assertStringContainsString(
            "app_dir='/opt/xdeploy/apps/wordpress'",
            $command,
        );
        self::assertStringContainsString(
            '-p xdeploy-wordpress',
            $command,
        );
        self::assertStringContainsString(
            "grep -Fxq 'database'",
            $command,
        );
        self::assertStringContainsString(
            "grep -Fxq 'wordpress'",
            $command,
        );
        self::assertStringContainsString(
            "grep -Fq 'XDEPLOY_WORDPRESS_PUBLIC_URL:'",
            $command,
        );
        self::assertStringContainsString(
            "grep -Fq 'WORDPRESS_CONFIG_EXTRA:'",
            $command,
        );
    }

    public function test_inspect_reports_enabled_only_when_runtime_caddy_and_https_match(): void
    {
        $ssh = new WordPressGatewayFakeSshConnection([
            new SSHResult(
                output: 'public_url=https://blog.example.com',
                exitCode: 0,
            ),
            new SSHResult(
                output: '',
                exitCode: 0,
            ),
        ]);

        $caddySites = $this->createMock(
            CaddySiteReaderInterface::class,
        );
        $caddySites->expects(self::once())
            ->method('exists')
            ->willReturn(true);
        $caddySites->expects(self::once())
            ->method('matches')
            ->with(self::callback(function (CaddySite $site): bool {
                self::assertSame('wordpress', $site->key->value);
                self::assertSame('blog.example.com', $site->domain);
                self::assertSame('127.0.0.1:8080', $site->upstream);

                return true;
            }))
            ->willReturn(true);

        $result = $this->gateway($ssh, $caddySites)->inspect();

        self::assertSame(PublicEndpointRuntimeState::Enabled, $result->state);
        self::assertSame('blog.example.com', $result->domain);
        self::assertStringContainsString(
            "'https://blog.example.com/'",
            $ssh->remoteCommands()[1] ?? '',
        );
    }

    public function test_enable_removes_a_new_caddy_site_when_runtime_preparation_fails(): void
    {
        $ssh = new WordPressGatewayFakeSshConnection([
            new SSHResult(
                output: implode("\n", [
                    'xdeploy_wordpress_endpoint_runtime=1',
                    'status=failed',
                    'stage=candidate',
                    'configuration_restored=0',
                    'services_recovered=0',
                ]),
                exitCode: 71,
            ),
        ]);

        $caddySites = $this->createMock(
            CaddySiteReaderInterface::class,
        );
        $caddySites->expects(self::once())
            ->method('environmentReady')
            ->willReturn(true);
        $caddySites->expects(self::once())
            ->method('exists')
            ->willReturn(false);

        $caddySiteManager = $this->createMock(
            CaddySiteManagerInterface::class,
        );
        $caddySiteManager->expects(self::once())
            ->method('upsert')
            ->willReturnCallback(
                static fn (CaddySite $site): CaddySiteMutationResult => new CaddySiteMutationResult(
                    key: $site->key,
                    changed: true,
                ),
            );
        $caddySiteManager->expects(self::once())
            ->method('remove')
            ->with(self::callback(
                static fn (CaddySiteKey $key): bool => $key->value === 'wordpress',
            ))
            ->willReturnCallback(
                static fn (CaddySiteKey $key): CaddySiteMutationResult => new CaddySiteMutationResult(
                    key: $key,
                    changed: true,
                ),
            );

        try {
            $this->gateway(
                ssh: $ssh,
                caddySites: $caddySites,
                caddySiteManager: $caddySiteManager,
            )->enable(
                PublicEndpointDomain::from('blog.example.com'),
            );
            self::fail('Expected a public endpoint candidate validation exception.');
        } catch (PublicEndpointOperationException $exception) {
            self::assertSame(
                PublicEndpointOperationFailure::CandidateValidation,
                $exception->failure,
            );
        }
    }

    private function gateway(
        SSHConnectionInterface $ssh,
        CaddySiteReaderInterface $caddySites,
        ?CaddySiteManagerInterface $caddySiteManager = null,
    ): SshWordPressPublicEndpointGateway {
        $privileged = new PrivilegedCommandExecutor(
            ssh: $ssh,
            preflight: new PrivilegedExecutionPreflight($ssh),
        );

        return new SshWordPressPublicEndpointGateway(
            ssh: $ssh,
            privileged: $privileged,
            dnsPreflight: new SshPublicEndpointDnsPreflight($ssh),
            runtime: new SshWordPressPublicEndpointRuntimeManager($privileged),
            caddySites: $caddySites,
            caddySiteManager: $caddySiteManager ?? $this->createMock(
                CaddySiteManagerInterface::class,
            ),
        );
    }
}

final class WordPressGatewayFakeSshConnection implements SSHConnectionInterface
{
    /** @var list<string> */
    private array $remoteCommands = [];

    /** @param list<SSHResult> $remoteResults */
    public function __construct(
        private array $remoteResults,
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

        $this->remoteCommands[] = $command;

        return array_shift($this->remoteResults)
            ?? new SSHResult(
                output: '',
                exitCode: 1,
            );
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function disconnect(): void {}

    /** @return list<string> */
    public function remoteCommands(): array
    {
        return $this->remoteCommands;
    }
}
