<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Platform\Services;

use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\Contracts\PlatformRegistryInterface;
use App\Domain\Platform\Contracts\StartablePlatformInterface;
use App\Domain\Platform\DTOs\PlatformInfo;
use App\Domain\Platform\Enums\PlatformState;
use App\Domain\Platform\Enums\PlatformType;
use App\Domain\Platform\Exceptions\PlatformInspectionException;
use App\Domain\Platform\Services\PlatformInstallationService;
use App\Domain\Server\Contracts\SystemPackageManager;
use App\Domain\Server\Services\SystemDependencyService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlatformInstallationServiceTest extends TestCase
{
    public function test_it_skips_installation_and_start_when_platform_is_already_running(): void
    {
        $platform = $this->startablePlatformMock();

        $platform
            ->expects($this->exactly(2))
            ->method('inspect')
            ->willReturn(
                $this->info(PlatformState::Running),
            );

        $platform
            ->expects($this->once())
            ->method('dependencies')
            ->willReturn([]);

        $platform
            ->expects($this->once())
            ->method('systemPackages')
            ->willReturn([]);

        $platform
            ->expects($this->never())
            ->method('install');

        $platform
            ->expects($this->never())
            ->method('start');

        $service = $this->serviceWithPlatform(
            type: PlatformType::Docker,
            platform: $platform,
        );

        $report = $service->ensure(
            PlatformType::Docker,
        );

        $this->assertSame(
            [
                [
                    'component' => 'docker',
                    'message' => 'Already installed.',
                ],
                [
                    'component' => 'docker',
                    'message' => 'Already running.',
                ],
            ],
            $this->messages($report->messages),
        );
    }

    public function test_it_starts_an_installed_but_stopped_platform_without_reinstalling_it(): void
    {
        $platform = $this->startablePlatformMock();

        $platform
            ->expects($this->exactly(3))
            ->method('inspect')
            ->willReturnOnConsecutiveCalls(
                $this->info(PlatformState::Installed),
                $this->info(PlatformState::Installed),
                $this->info(PlatformState::Running),
            );

        $platform
            ->expects($this->once())
            ->method('dependencies')
            ->willReturn([]);

        $platform
            ->expects($this->once())
            ->method('systemPackages')
            ->willReturn([]);

        $platform
            ->expects($this->never())
            ->method('install');

        $platform
            ->expects($this->once())
            ->method('start');

        $service = $this->serviceWithPlatform(
            type: PlatformType::Docker,
            platform: $platform,
        );

        $report = $service->ensure(
            PlatformType::Docker,
        );

        $this->assertSame(
            [
                [
                    'component' => 'docker',
                    'message' => 'Already installed.',
                ],
                [
                    'component' => 'docker',
                    'message' => 'Started successfully.',
                ],
            ],
            $this->messages($report->messages),
        );
    }

    public function test_it_installs_starts_and_verifies_a_missing_platform(): void
    {
        $platform = $this->startablePlatformMock();

        $platform
            ->expects($this->exactly(4))
            ->method('inspect')
            ->willReturnOnConsecutiveCalls(
                $this->info(PlatformState::NotInstalled),
                $this->info(PlatformState::NotInstalled),
                $this->info(PlatformState::Installed),
                $this->info(PlatformState::Running),
            );

        $platform
            ->expects($this->once())
            ->method('dependencies')
            ->willReturn([]);

        $platform
            ->expects($this->once())
            ->method('systemPackages')
            ->willReturn([]);

        $platform
            ->expects($this->once())
            ->method('install');

        $platform
            ->expects($this->once())
            ->method('start');

        $service = $this->serviceWithPlatform(
            type: PlatformType::Docker,
            platform: $platform,
        );

        $report = $service->ensure(
            PlatformType::Docker,
        );

        $this->assertSame(
            [
                [
                    'component' => 'docker',
                    'message' => 'Installed successfully.',
                ],
                [
                    'component' => 'docker',
                    'message' => 'Started successfully.',
                ],
            ],
            $this->messages($report->messages),
        );
    }

    public function test_it_ensures_docker_before_docker_compose(): void
    {
        $docker = $this->startablePlatformMock();

        $docker
            ->expects($this->exactly(2))
            ->method('inspect')
            ->willReturn(
                $this->info(PlatformState::Running),
            );

        $docker
            ->expects($this->once())
            ->method('dependencies')
            ->willReturn([]);

        $docker
            ->expects($this->once())
            ->method('systemPackages')
            ->willReturn([]);

        $docker
            ->expects($this->never())
            ->method('install');

        $docker
            ->expects($this->never())
            ->method('start');

        $compose = $this->createMock(
            PlatformInterface::class,
        );

        $compose
            ->expects($this->exactly(2))
            ->method('inspect')
            ->willReturn(
                $this->info(PlatformState::Installed),
            );

        $compose
            ->expects($this->once())
            ->method('dependencies')
            ->willReturn([
                PlatformType::Docker,
            ]);

        $compose
            ->expects($this->once())
            ->method('systemPackages')
            ->willReturn([]);

        $compose
            ->expects($this->never())
            ->method('install');

        $registry = $this->createMock(
            PlatformRegistryInterface::class,
        );

        $registry
            ->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(
                static fn (
                    PlatformType $type,
                ): PlatformInterface => match ($type) {
                    PlatformType::DockerCompose => $compose,
                    PlatformType::Docker => $docker,
                },
            );

        $service = new PlatformInstallationService(
            registry: $registry,
            systemDependencies: $this->emptySystemDependencies(),
        );

        $report = $service->ensure(
            PlatformType::DockerCompose,
        );

        $this->assertSame(
            [
                [
                    'component' => 'docker',
                    'message' => 'Already installed.',
                ],
                [
                    'component' => 'docker',
                    'message' => 'Already running.',
                ],
                [
                    'component' => 'docker-compose',
                    'message' => 'Already installed.',
                ],
            ],
            $this->messages($report->messages),
        );
    }

    public function test_it_rejects_an_unknown_platform_state(): void
    {
        $platform = $this->startablePlatformMock();

        $platform
            ->expects($this->exactly(3))
            ->method('inspect')
            ->willReturn(
                $this->info(PlatformState::Unknown),
            );

        $platform
            ->expects($this->never())
            ->method('dependencies');

        $platform
            ->expects($this->never())
            ->method('systemPackages');

        $platform
            ->expects($this->never())
            ->method('install');

        $platform
            ->expects($this->never())
            ->method('start');

        $service = $this->serviceWithPlatform(
            type: PlatformType::Docker,
            platform: $platform,
        );

        $this->expectException(
            PlatformInspectionException::class,
        );

        $service->ensure(
            PlatformType::Docker,
        );
    }

    private function serviceWithPlatform(
        PlatformType $type,
        PlatformInterface $platform,
    ): PlatformInstallationService {
        $registry = $this->createMock(
            PlatformRegistryInterface::class,
        );

        $registry
            ->expects($this->once())
            ->method('find')
            ->with($type)
            ->willReturn($platform);

        return new PlatformInstallationService(
            registry: $registry,
            systemDependencies: $this->emptySystemDependencies(),
        );
    }

    private function emptySystemDependencies(): SystemDependencyService
    {
        $packageManager = $this->createMock(
            SystemPackageManager::class,
        );

        $packageManager
            ->expects($this->never())
            ->method('isInstalled');

        $packageManager
            ->expects($this->never())
            ->method('install');

        return new SystemDependencyService(
            packageManager: $packageManager,
        );
    }

    /**
     * @return MockObject&PlatformInterface&StartablePlatformInterface
     */
    private function startablePlatformMock(): MockObject
    {
        return $this->createMockForIntersectionOfInterfaces([
            PlatformInterface::class,
            StartablePlatformInterface::class,
        ]);
    }

    private function info(
        PlatformState $state,
    ): PlatformInfo {
        return new PlatformInfo(
            state: $state,
        );
    }

    /**
     * @param  array<int, object>  $messages
     * @return list<array{
     *     component: string,
     *     message: string
     * }>
     */
    private function messages(array $messages): array
    {
        return array_map(
            static fn (object $message): array => [
                'component' => $message->component,
                'message' => $message->message,
            ],
            $messages,
        );
    }
}
