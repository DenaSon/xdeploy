<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Server\Services;

use App\Domain\Server\Contracts\SystemPackageManager;
use App\Domain\Server\Services\SystemDependencyService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SystemDependencyServiceTest extends TestCase
{
    public function test_it_returns_an_empty_report_when_no_packages_are_required(): void
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

        $service = new SystemDependencyService(
            packageManager: $packageManager,
        );

        $report = $service->ensure([]);

        $this->assertSame(
            [],
            $report->messages,
        );
    }

    public function test_it_installs_only_missing_packages_in_one_batch_and_returns_a_report(): void
    {
        $packageManager = $this->createMock(
            SystemPackageManager::class,
        );

        /** @var array<string, int> $checks */
        $checks = [];

        $packageManager
            ->expects($this->exactly(5))
            ->method('isInstalled')
            ->willReturnCallback(
                static function (
                    string $package,
                ) use (&$checks): bool {
                    $checks[$package] =
                        ($checks[$package] ?? 0) + 1;

                    return match ($package) {
                        'curl' => true,

                        'ca-certificates',
                        'gnupg' => $checks[$package] > 1,

                        default => false,
                    };
                },
            );

        $packageManager
            ->expects($this->once())
            ->method('install')
            ->with([
                'ca-certificates',
                'gnupg',
            ]);

        $service = new SystemDependencyService(
            packageManager: $packageManager,
        );

        $report = $service->ensure([
            ' curl ',
            'ca-certificates',
            'curl',
            'gnupg',
            '',
        ]);

        $messages = array_map(
            static fn ($message): array => [
                'component' => $message->component,
                'message' => $message->message,
            ],
            $report->messages,
        );

        $this->assertSame(
            [
                [
                    'component' => 'curl',
                    'message' => 'Already installed.',
                ],
                [
                    'component' => 'ca-certificates',
                    'message' => 'Installed successfully.',
                ],
                [
                    'component' => 'gnupg',
                    'message' => 'Installed successfully.',
                ],
            ],
            $messages,
        );
    }

    public function test_it_fails_when_a_package_is_not_installed_after_installation(): void
    {
        $packageManager = $this->createMock(
            SystemPackageManager::class,
        );

        $packageManager
            ->expects($this->exactly(2))
            ->method('isInstalled')
            ->with('curl')
            ->willReturn(false);

        $packageManager
            ->expects($this->once())
            ->method('install')
            ->with(['curl']);

        $service = new SystemDependencyService(
            packageManager: $packageManager,
        );

        $this->expectException(
            RuntimeException::class,
        );

        $this->expectExceptionMessage(
            'System package installation verification failed: curl.',
        );

        $service->ensure(['curl']);
    }
}
