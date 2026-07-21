<?php

declare(strict_types=1);

namespace Tests\Integration\SSH;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use Tests\TestCase;

class SSHConnectionTest extends TestCase
{
    protected SSHConnectionInterface $ssh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ssh = app(SSHConnectionInterface::class);

        $connected = $this->ssh->connect(
            host: env('TEST_SSH_HOST'),
            port: (int) env('TEST_SSH_PORT', 22),
            username: env('TEST_SSH_USERNAME'),
            authenticationType: env('TEST_SSH_AUTH', 'password'),
            credential: env('TEST_SSH_PASSWORD'),
            privateKeyPath: env('TEST_SSH_PRIVATE_KEY'),
        );

        $this->assertTrue($connected);
    }

    public function test_can_connect_to_test_server(): void
    {
        $this->assertTrue($this->ssh->isConnected());
    }

    public function test_can_execute_commands(): void
    {
        $result = $this->ssh->executeWithResult('pwd');

        $this->assertTrue($result->successful());
        $this->assertSame('/root', $result->output);
        $this->assertSame(0, $result->exitCode);
    }

    public function test_returns_command_output(): void
    {
        $result = $this->ssh->executeWithResult('echo "Hello xDeploy"');

        $this->assertTrue($result->successful());
        $this->assertSame('Hello xDeploy', $result->output);
        $this->assertSame(0, $result->exitCode);
    }

    public function test_returns_correct_exit_codes(): void
    {
        $success = $this->ssh->executeWithResult('true');
        $failure = $this->ssh->executeWithResult('false');
        $missing = $this->ssh->executeWithResult('command_that_does_not_exist');

        $this->assertSame(0, $success->exitCode);
        $this->assertSame(1, $failure->exitCode);
        $this->assertSame(127, $missing->exitCode);
    }

    public function test_can_disconnect(): void
    {
        $this->ssh->disconnect();

        $this->assertFalse($this->ssh->isConnected());
    }

    public function test_reconnects_automatically_after_disconnect(): void
    {
        $this->ssh->disconnect();

        $result = $this->ssh->executeWithResult('pwd');

        $this->assertTrue($this->ssh->isConnected());
        $this->assertTrue($result->successful());
        $this->assertSame('/root', $result->output);
    }
}
