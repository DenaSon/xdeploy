<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Application\Marzban\Https;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteManagerInterface;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteReaderInterface;
use App\Domain\Platform\Caddy\Sites\DTOs\CaddySiteMutationResult;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\Application\Marzban\Https\SshMarzbanHttpsDisabler;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class SshMarzbanHttpsDisablerTest extends TestCase
{
    public function test_it_removes_the_managed_caddy_site_and_keeps_marzban_private(): void
    {
        $ssh = new DisableRuntimeFakeSsh;
        $reader = new DisableRuntimeFakeCaddySiteReader;
        $sites = new DisableRuntimeFakeCaddySiteManager;

        $this->disabler(
            ssh: $ssh,
            reader: $reader,
            sites: $sites,
        )->disable(
            MarzbanDomain::from('panel.example.com'),
        );

        self::assertSame('marzban', $sites->removed?->value);
        self::assertNull($sites->upserted);
        self::assertNotNull($reader->matchedSite);
        self::assertSame('panel.example.com', $reader->matchedSite->domain);
        self::assertSame('127.0.0.1:8000', $reader->matchedSite->upstream);

        $commands = implode("\n", $ssh->commands);

        self::assertStringContainsString(
            'xdeploy_marzban_https_disable=1',
            $commands,
        );
        self::assertStringContainsString(
            'UVICORN_HOST=127.0.0.1',
            $commands,
        );
        self::assertStringContainsString(
            'UVICORN_PORT=8000',
            $commands,
        );
        self::assertStringContainsString(
            'XRAY_SUBSCRIPTION_URL_PREFIX',
            $commands,
        );
        self::assertStringContainsString(
            'http://127.0.0.1:8000/dashboard/',
            $commands,
        );
    }

    public function test_it_never_removes_a_caddy_site_that_does_not_match_the_endpoint(): void
    {
        $ssh = new DisableRuntimeFakeSsh;
        $reader = new DisableRuntimeFakeCaddySiteReader;
        $reader->matchesSite = false;
        $sites = new DisableRuntimeFakeCaddySiteManager;

        try {
            $this->disabler(
                ssh: $ssh,
                reader: $reader,
                sites: $sites,
            )->disable(
                MarzbanDomain::from('panel.example.com'),
            );

            self::fail('Expected an apply exception.');
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::ExistingConfiguration,
                $exception->failure,
            );
        }

        self::assertNull($sites->removed);
        self::assertSame([], $ssh->commands);
    }

    public function test_it_restores_the_caddy_site_when_runtime_cleanup_fails(): void
    {
        $ssh = new DisableRuntimeFakeSsh(
            failDisable: true,
        );
        $reader = new DisableRuntimeFakeCaddySiteReader;
        $sites = new DisableRuntimeFakeCaddySiteManager;

        try {
            $this->disabler(
                ssh: $ssh,
                reader: $reader,
                sites: $sites,
            )->disable(
                MarzbanDomain::from('panel.example.com'),
            );

            self::fail('Expected an apply exception.');
        } catch (MarzbanHttpsApplyException $exception) {
            self::assertSame(
                MarzbanHttpsApplyFailure::Mutation,
                $exception->failure,
            );
            self::assertTrue($exception->recovered());
        }

        self::assertSame('marzban', $sites->removed?->value);
        self::assertNotNull($sites->upserted);
        self::assertSame('panel.example.com', $sites->upserted->domain);
    }

    public function test_it_is_idempotent_when_the_managed_site_is_already_absent(): void
    {
        $ssh = new DisableRuntimeFakeSsh;
        $reader = new DisableRuntimeFakeCaddySiteReader;
        $reader->siteExists = false;
        $sites = new DisableRuntimeFakeCaddySiteManager;

        $this->disabler(
            ssh: $ssh,
            reader: $reader,
            sites: $sites,
        )->disable(
            MarzbanDomain::from('panel.example.com'),
        );

        self::assertNull($sites->removed);
        self::assertSame([], $ssh->commands);
    }

    private function disabler(
        SSHConnectionInterface $ssh,
        CaddySiteReaderInterface $reader,
        CaddySiteManagerInterface $sites,
    ): SshMarzbanHttpsDisabler {
        return new SshMarzbanHttpsDisabler(
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    ssh: $ssh,
                ),
            ),
            caddySites: $reader,
            caddySiteManager: $sites,
        );
    }
}

final class DisableRuntimeFakeCaddySiteReader implements CaddySiteReaderInterface
{
    public bool $siteExists = true;

    public bool $matchesSite = true;

    public ?CaddySite $matchedSite = null;

    public function environmentReady(): bool
    {
        return true;
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

final class DisableRuntimeFakeCaddySiteManager implements CaddySiteManagerInterface
{
    public ?CaddySiteKey $removed = null;

    public ?CaddySite $upserted = null;

    public function upsert(CaddySite $site): CaddySiteMutationResult
    {
        $this->upserted = $site;

        return new CaddySiteMutationResult(
            key: $site->key,
            changed: true,
        );
    }

    public function remove(CaddySiteKey $key): CaddySiteMutationResult
    {
        $this->removed = $key;

        return new CaddySiteMutationResult(
            key: $key,
            changed: true,
        );
    }
}

final class DisableRuntimeFakeSsh implements SSHConnectionInterface
{
    /**
     * @var list<string>
     */
    public array $commands = [];

    public function __construct(
        private readonly bool $failDisable = false,
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

        if (str_contains($command, 'xdeploy_marzban_https_disable=1')) {
            if ($this->failDisable) {
                return new SSHResult(
                    output: <<<'OUTPUT'
xdeploy_marzban_https_disable=1
status=failed
stage=mutation
configuration_restored=1
services_recovered=1
OUTPUT,
                    exitCode: 72,
                );
            }

            return new SSHResult(
                output: <<<'OUTPUT'
xdeploy_marzban_https_disable=1
status=disabled
configuration_changed=1
OUTPUT,
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
