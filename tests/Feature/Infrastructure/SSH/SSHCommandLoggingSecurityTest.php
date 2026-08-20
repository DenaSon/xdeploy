<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\SSH;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
use App\Models\Server;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use phpseclib3\Net\SSH2;
use ReflectionProperty;
use Tests\TestCase;

final class SSHCommandLoggingSecurityTest extends TestCase
{
    #[DataProvider('applicationEnvironments')]
    public function test_sensitive_command_body_is_never_logged(
        string $environment,
    ): void
    {
        $this->app->instance('env', $environment);

        $secretCommand = "printf '%s' 'command-secret-{$environment}'";

        $connection = $this->connectedConnection(
            command: $secretCommand,
            output: '',
            exitCode: 0,
        );

        $records = $this->captureLogRecords(
            static fn () => $connection->executeWithResult(
                command: $secretCommand,
                sensitive: true,
            ),
        );

        $started = $this->recordFor(
            records: $records,
            message: 'ssh.command.started',
        );

        self::assertSame('[hidden]', $started->context['command'] ?? null);
        self::assertTrue($started->context['sensitive'] ?? false);
        $this->assertRecordsDoNotContain(
            records: $records,
            sensitiveValue: $secretCommand,
        );
    }

    public function test_sensitive_command_output_excerpt_is_never_logged(): void
    {
        $secretCommand = "printf '%s' 'command-secret'";
        $secretOutput = 'generated-password=output-secret';

        $connection = $this->connectedConnection(
            command: $secretCommand,
            output: $secretOutput,
            exitCode: 1,
        );

        $records = $this->captureLogRecords(
            static fn () => $connection->executeWithResult(
                command: $secretCommand,
                sensitive: true,
            ),
        );

        $completed = $this->recordFor(
            records: $records,
            message: 'ssh.command.completed',
        );

        self::assertFalse($completed->context['successful'] ?? true);
        self::assertTrue($completed->context['sensitive'] ?? false);
        self::assertArrayNotHasKey(
            'output_excerpt',
            $completed->context,
        );
        $this->assertRecordsDoNotContain(
            records: $records,
            sensitiveValue: $secretOutput,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function applicationEnvironments(): array
    {
        return [
            'local' => ['local'],
            'production' => ['production'],
        ];
    }

    private function connectedConnection(
        string $command,
        string $output,
        int $exitCode,
    ): SSHConnection {
        $connection = app(
            SSHConnectionInterface::class,
        );

        self::assertInstanceOf(
            SSHConnection::class,
            $connection,
        );

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
            ->with($command)
            ->willReturn($output);
        $ssh->method('isTimeout')
            ->willReturn(false);
        $ssh->method('getExitStatus')
            ->willReturn($exitCode);

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

        return $connection;
    }

    /**
     * @return list<LogRecord>
     */
    private function captureLogRecords(callable $callback): array
    {
        $handler = new TestHandler;
        $logger = logger()->getLogger();
        $logger->pushHandler($handler);

        try {
            $callback();

            return $handler->getRecords();
        } finally {
            $logger->popHandler();
        }
    }

    /**
     * @param  list<LogRecord>  $records
     */
    private function recordFor(
        array $records,
        string $message,
    ): LogRecord {
        foreach ($records as $record) {
            if ($record->message === $message) {
                return $record;
            }
        }

        self::fail("Expected log record [{$message}] was not emitted.");
    }

    /**
     * @param  list<LogRecord>  $records
     */
    private function assertRecordsDoNotContain(
        array $records,
        string $sensitiveValue,
    ): void {
        foreach ($records as $record) {
            $serializedRecord = json_encode(
                [
                    'message' => $record->message,
                    'context' => $record->context,
                ],
                JSON_THROW_ON_ERROR,
            );

            self::assertStringNotContainsString(
                $sensitiveValue,
                $serializedRecord,
                "Sensitive value leaked through log [{$record->message}].",
            );
        }
    }
}
