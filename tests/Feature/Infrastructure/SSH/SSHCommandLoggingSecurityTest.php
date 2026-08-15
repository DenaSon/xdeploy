<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\SSH;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;
use ReflectionProperty;
use Tests\TestCase;

final class SSHCommandLoggingSecurityTest extends TestCase
{
    public function test_sensitive_command_body_is_hidden_even_in_local_environment(): void
    {
        $this->app->instance('env', 'local');
        Log::spy();

        $connection = app(
            SSHConnectionInterface::class,
        );

        self::assertInstanceOf(
            SSHConnection::class,
            $connection,
        );

        $secretCommand = "printf '%s' 'super-secret-value'";

        $server = new Server([
            'host' => '192.0.2.20',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => 'password',
        ]);
        $server->setAttribute('id', 321);
        $server->exists = true;

        $ssh = $this->createMock(
            SSH2::class,
        );

        $ssh->method('isAuthenticated')
            ->willReturn(true);
        $ssh->method('exec')
            ->with($secretCommand)
            ->willReturn('');
        $ssh->method('isTimeout')
            ->willReturn(false);
        $ssh->method('getExitStatus')
            ->willReturn(0);

        (new ReflectionProperty(
            SSHConnection::class,
            'server',
        ))->setValue(
            $connection,
            $server,
        );

        (new ReflectionProperty(
            SSHConnection::class,
            'ssh',
        ))->setValue(
            $connection,
            $ssh,
        );

        $connection->executeWithResult(
            command: $secretCommand,
            sensitive: true,
        );

        Log::shouldHaveReceived('info')
            ->withArgs(
                static function (
                    string $message,
                    array $context,
                ) use ($secretCommand): bool {
                    if ($message !== 'ssh.command.started') {
                        return false;
                    }

                    return ($context['command'] ?? null) === '[hidden]'
                        && ($context['sensitive'] ?? null) === true
                        && ! str_contains(
                            json_encode(
                                $context,
                                JSON_THROW_ON_ERROR,
                            ),
                            $secretCommand,
                        );
                },
            );
    }
}
