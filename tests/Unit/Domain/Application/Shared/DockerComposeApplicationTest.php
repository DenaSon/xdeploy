<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Application\Shared;

use App\Domain\Application\Shared\Abstracts\DockerComposeApplication;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use PHPUnit\Framework\TestCase;

final class DockerComposeApplicationTest extends TestCase
{
    public function test_single_service_applications_keep_the_existing_running_state_behavior(): void
    {
        $ssh = new DockerComposeApplicationFakeSshConnection([
            'primary',
        ]);

        $application = $this->application(
            ssh: $ssh,
        );

        self::assertSame(
            ApplicationState::Running,
            $application->inspect()->state,
        );

        $commands = $ssh->dockerInspectionCommands();

        self::assertCount(1, $commands);
        self::assertStringContainsString(
            'label=com.docker.compose.service=primary',
            $commands[0],
        );
    }

    public function test_multi_service_application_is_running_only_when_all_required_services_run(): void
    {
        $ssh = new DockerComposeApplicationFakeSshConnection([
            'web',
            'database',
        ]);

        $application = $this->application(
            ssh: $ssh,
            requiredServices: [
                'web',
                'database',
            ],
        );

        self::assertSame(
            ApplicationState::Running,
            $application->inspect()->state,
        );

        self::assertCount(
            2,
            $ssh->dockerInspectionCommands(),
        );
    }

    public function test_multi_service_application_is_installed_when_no_required_service_runs(): void
    {
        $application = $this->application(
            ssh: new DockerComposeApplicationFakeSshConnection,
            requiredServices: [
                'web',
                'database',
            ],
        );

        self::assertSame(
            ApplicationState::Installed,
            $application->inspect()->state,
        );
    }

    public function test_partial_multi_service_runtime_is_unknown(): void
    {
        $application = $this->application(
            ssh: new DockerComposeApplicationFakeSshConnection([
                'web',
            ]),
            requiredServices: [
                'web',
                'database',
            ],
        );

        self::assertSame(
            ApplicationState::Unknown,
            $application->inspect()->state,
        );
    }

    public function test_failed_required_service_inspection_is_unknown(): void
    {
        $application = $this->application(
            ssh: new DockerComposeApplicationFakeSshConnection(
                runningServices: [
                    'web',
                ],
                failingServices: [
                    'database',
                ],
            ),
            requiredServices: [
                'web',
                'database',
            ],
        );

        self::assertSame(
            ApplicationState::Unknown,
            $application->inspect()->state,
        );
    }

    public function test_empty_required_service_list_is_unknown(): void
    {
        $ssh = new DockerComposeApplicationFakeSshConnection;

        $application = $this->application(
            ssh: $ssh,
            requiredServices: [],
        );

        self::assertSame(
            ApplicationState::Unknown,
            $application->inspect()->state,
        );

        self::assertSame(
            [],
            $ssh->dockerInspectionCommands(),
        );
    }

    /**
     * @param  list<string>|null  $requiredServices
     */
    private function application(
        SSHConnectionInterface $ssh,
        ?array $requiredServices = null,
    ): DockerComposeApplicationTestApplication {
        return new DockerComposeApplicationTestApplication(
            ssh: $ssh,
            privileged: new PrivilegedCommandExecutor(
                ssh: $ssh,
                preflight: new PrivilegedExecutionPreflight(
                    ssh: $ssh,
                ),
            ),
            requiredServices: $requiredServices,
        );
    }
}

final readonly class DockerComposeApplicationTestApplication extends DockerComposeApplication
{
    /**
     * @param  list<string>|null  $requiredServices
     */
    public function __construct(
        SSHConnectionInterface $ssh,
        PrivilegedCommandExecutor $privileged,
        private ?array $requiredServices = null,
    ) {
        parent::__construct(
            ssh: $ssh,
            privileged: $privileged,
        );
    }

    public function type(): ApplicationType
    {
        return ApplicationType::N8n;
    }

    public function name(): string
    {
        return 'Test Application';
    }

    protected function inspectCommand(): string
    {
        return 'inspect-test-application';
    }

    protected function installCommand(): string
    {
        return 'install-test-application';
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadataFromOutput(
        string $output,
    ): array {
        return [];
    }

    protected function composeProject(): string
    {
        return 'test-application';
    }

    protected function composeService(): string
    {
        return 'primary';
    }

    /**
     * @return list<string>
     */
    protected function requiredComposeServices(): array
    {
        return $this->requiredServices
            ?? parent::requiredComposeServices();
    }

    protected function composeFile(): string
    {
        return '/opt/test-application/docker-compose.yml';
    }

    protected function composeEnvFile(): string
    {
        return '/opt/test-application/.env';
    }
}

final class DockerComposeApplicationFakeSshConnection implements SSHConnectionInterface
{
    /** @var list<string> */
    public array $commands = [];

    /**
     * @param  list<string>  $runningServices
     * @param  list<string>  $failingServices
     */
    public function __construct(
        private readonly array $runningServices = [],
        private readonly array $failingServices = [],
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

        if ($command === 'inspect-test-application') {
            return new SSHResult(
                output: '',
                exitCode: 0,
            );
        }

        if (str_contains($command, 'docker ps')) {
            preg_match(
                '/label=com\\.docker\\.compose\\.service=([^"]+)/',
                $command,
                $matches,
            );

            $service = $matches[1] ?? null;

            if (
                is_string($service)
                && in_array(
                    $service,
                    $this->failingServices,
                    true,
                )
            ) {
                return new SSHResult(
                    output: 'Docker inspection failed.',
                    exitCode: 1,
                );
            }

            return new SSHResult(
                output: is_string($service)
                    && in_array(
                        $service,
                        $this->runningServices,
                        true,
                    )
                        ? 'container-'.$service
                        : '',
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

    /** @return list<string> */
    public function dockerInspectionCommands(): array
    {
        return array_values(
            array_filter(
                $this->commands,
                static fn (string $command): bool => str_contains(
                    $command,
                    'docker ps',
                ),
            ),
        );
    }
}
