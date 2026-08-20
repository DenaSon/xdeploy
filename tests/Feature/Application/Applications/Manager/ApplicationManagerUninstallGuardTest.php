<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Applications\Manager;

use App\Application\Applications\Manager\ApplicationManager;
use App\Application\Applications\Operations\Exceptions\ApplicationUninstallBlockedByPublicEndpointException;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\PublicEndpoint;
use App\Models\Server;
use App\Models\User;
use App\Support\SSH\SSHTimeout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApplicationManagerUninstallGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_rechecks_the_endpoint_before_remote_uninstall_mutation(): void
    {
        $user = User::query()->create([
            'name' => 'Application Manager Guard Test',
            'phone' => '09120000061',
        ]);

        $server = $user->servers()->create([
            'name' => 'Application Manager Guard Server',
            'host' => '192.0.2.61',
            'port' => 22,
            'username' => 'root',
        ]);

        PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n,
            'domain' => 'automation.example.com',
            'activated_at' => now(),
        ]);

        $ssh = new ApplicationManagerUninstallGuardFakeSshConnection;

        $this->app->instance(
            SSHConnectionInterface::class,
            $ssh,
        );

        $this->expectException(
            ApplicationUninstallBlockedByPublicEndpointException::class,
        );

        try {
            $this->app
                ->make(ApplicationManager::class)
                ->uninstall(
                    user: $user,
                    server: $server,
                    type: ApplicationType::N8n,
                );
        } finally {
            self::assertSame([], $ssh->commands);
        }
    }
}

final class ApplicationManagerUninstallGuardFakeSshConnection implements SSHConnectionInterface
{
    public int $connections = 0;

    /** @var list<string> */
    public array $commands = [];

    public function connect(Server $server): bool
    {
        $this->connections++;

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

        return new SSHResult(
            output: '',
            exitCode: 0,
        );
    }

    public function isConnected(): bool
    {
        return $this->connections > 0;
    }

    public function disconnect(): void {}
}
