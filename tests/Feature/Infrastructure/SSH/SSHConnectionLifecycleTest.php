<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\SSH;

use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Platform\Contracts\PlatformRegistryInterface;
use App\Domain\Server\Contracts\SystemPackageManager;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use phpseclib3\Net\SSH2;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

final class SSHConnectionLifecycleTest extends TestCase
{
    public function test_ssh_connection_is_shared_within_the_same_scope(): void
    {
        $first = app(
            SSHConnectionInterface::class,
        );

        $second = app(
            SSHConnectionInterface::class,
        );

        self::assertSame(
            $first,
            $second,
        );
    }

    public function test_authenticated_transport_enables_keepalive(): void
    {
        $connection = app(
            SSHConnectionInterface::class,
        );

        self::assertInstanceOf(
            SSHConnection::class,
            $connection,
        );

        $ssh = $this->createMock(
            SSH2::class,
        );

        $ssh->expects(self::once())
            ->method('setKeepAlive')
            ->with(SSHTimeout::KEEPALIVE);

        $ssh->expects(self::once())
            ->method('setTimeout')
            ->with(SSHTimeout::DEFAULT);

        $method = new ReflectionMethod(
            SSHConnection::class,
            'configureAuthenticatedTransport',
        );

        $method->invoke(
            $connection,
            $ssh,
        );
    }

    public function test_ssh_connection_is_recreated_after_scope_is_flushed(): void
    {
        $first = app(
            SSHConnectionInterface::class,
        );

        $this->app->forgetScopedInstances();

        $second = app(
            SSHConnectionInterface::class,
        );

        self::assertNotSame(
            $first,
            $second,
        );

        self::assertInstanceOf(
            SSHConnection::class,
            $second,
        );
    }

    public function test_ssh_state_does_not_leak_into_the_next_scope(): void
    {
        $first = app(
            SSHConnectionInterface::class,
        );

        self::assertInstanceOf(
            SSHConnection::class,
            $first,
        );

        $serverProperty = new ReflectionProperty(
            SSHConnection::class,
            'server',
        );

        /*
         * Simulate state stored during one request/job.
         *
         * No real network connection is created.
         */
        $serverProperty->setValue(
            $first,
            new Server([
                'host' => '192.0.2.10',
                'port' => 22,
                'username' => 'root',
            ]),
        );

        self::assertInstanceOf(
            Server::class,
            $serverProperty->getValue(
                $first,
            ),
        );

        /*
         * Simulate Laravel starting the next request/job.
         */
        $this->app->forgetScopedInstances();

        $second = app(
            SSHConnectionInterface::class,
        );

        self::assertNotSame(
            $first,
            $second,
        );

        self::assertNull(
            $serverProperty->getValue(
                $second,
            ),
        );
    }

    public function test_repeated_connect_reuses_the_authenticated_transport_for_the_same_server(): void
    {
        $connection = app(
            SSHConnectionInterface::class,
        );

        self::assertInstanceOf(
            SSHConnection::class,
            $connection,
        );

        $serverProperty = new ReflectionProperty(
            SSHConnection::class,
            'server',
        );

        $sshProperty = new ReflectionProperty(
            SSHConnection::class,
            'ssh',
        );

        $server = new Server([
            'host' => '192.0.2.20',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => 'password',
        ]);

        $server->setAttribute('id', 123);
        $server->exists = true;

        $ssh = $this->createMock(
            SSH2::class,
        );

        $ssh->expects(self::once())
            ->method('isAuthenticated')
            ->willReturn(true);

        $ssh->expects(self::never())
            ->method('disconnect');

        $serverProperty->setValue(
            $connection,
            $server,
        );

        $sshProperty->setValue(
            $connection,
            $ssh,
        );

        self::assertTrue(
            $connection->connect(clone $server),
        );

        self::assertSame(
            $ssh,
            $sshProperty->getValue($connection),
        );
    }

    public function test_disconnect_retains_the_current_server_for_reconnect(): void
    {
        $connection = app(
            SSHConnectionInterface::class,
        );

        self::assertInstanceOf(
            SSHConnection::class,
            $connection,
        );

        $serverProperty = new ReflectionProperty(
            SSHConnection::class,
            'server',
        );

        $server = new Server([
            'host' => '192.0.2.20',
            'port' => 22,
            'username' => 'root',
        ]);

        $serverProperty->setValue(
            $connection,
            $server,
        );

        $connection->disconnect();

        self::assertSame(
            $server,
            $serverProperty->getValue(
                $connection,
            ),
        );
    }

    public function test_ssh_dependent_services_are_recreated_per_scope(): void
    {
        $packageManagerBefore = app(
            SystemPackageManager::class,
        );

        $applicationRegistryBefore = app(
            ApplicationRegistryInterface::class,
        );

        $platformRegistryBefore = app(
            PlatformRegistryInterface::class,
        );

        $this->app->forgetScopedInstances();

        $packageManagerAfter = app(
            SystemPackageManager::class,
        );

        $applicationRegistryAfter = app(
            ApplicationRegistryInterface::class,
        );

        $platformRegistryAfter = app(
            PlatformRegistryInterface::class,
        );

        self::assertNotSame(
            $packageManagerBefore,
            $packageManagerAfter,
        );

        self::assertNotSame(
            $applicationRegistryBefore,
            $applicationRegistryAfter,
        );

        self::assertNotSame(
            $platformRegistryBefore,
            $platformRegistryAfter,
        );
    }
}
