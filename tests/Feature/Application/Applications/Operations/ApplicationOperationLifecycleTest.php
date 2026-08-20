<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Applications\Operations;

use App\Application\Applications\Operations\Exceptions\ApplicationUninstallBlockedByPublicEndpointException;
use App\Application\Applications\Operations\RunApplicationOperationJob;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Server\Exceptions\SystemPackageManagerBusyException;
use App\Models\ApplicationOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class ApplicationOperationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_operation_can_enter_running_only_once_and_then_succeed(): void
    {
        $operation = $this->createOperation();

        self::assertTrue(
            $operation->markRunning(),
        );

        self::assertFalse(
            $operation->markRunning(),
        );

        $operation->refresh();

        self::assertSame(
            ApplicationOperationStatus::Running,
            $operation->status,
        );

        self::assertNotNull(
            $operation->started_at,
        );

        $operation->markSucceeded();
        $operation->refresh();

        self::assertSame(
            ApplicationOperationStatus::Succeeded,
            $operation->status,
        );

        self::assertNotNull(
            $operation->finished_at,
        );

        self::assertFalse(
            $operation->isActive(),
        );
    }

    public function test_queue_failure_callback_closes_a_running_operation(): void
    {
        $operation = $this->createOperation();

        self::assertTrue(
            $operation->markRunning(),
        );

        (new RunApplicationOperationJob(
            (int) $operation->getKey(),
        ))->failed(
            new RuntimeException('test queue failure'),
        );

        $operation->refresh();

        self::assertSame(
            ApplicationOperationStatus::Failed,
            $operation->status,
        );

        self::assertSame(
            'job_failed_with_exception',
            $operation->failure_code,
        );

        self::assertNotNull(
            $operation->finished_at,
        );
    }

    public function test_package_manager_busy_failure_uses_specific_failure_code(): void
    {
        $operation = $this->createOperation();

        self::assertTrue(
            $operation->markRunning(),
        );

        (new RunApplicationOperationJob(
            (int) $operation->getKey(),
        ))->failed(
            new RuntimeException(
                'wrapped package manager failure',
                previous: new SystemPackageManagerBusyException,
            ),
        );

        $operation->refresh();

        self::assertSame(
            ApplicationOperationStatus::Failed,
            $operation->status,
        );

        self::assertSame(
            'package_manager_busy',
            $operation->failure_code,
        );
    }

    public function test_active_public_endpoint_failure_uses_specific_failure_code(): void
    {
        $operation = $this->createOperation();

        self::assertTrue(
            $operation->markRunning(),
        );

        (new RunApplicationOperationJob(
            (int) $operation->getKey(),
        ))->failed(
            new RuntimeException(
                'wrapped public endpoint guard failure',
                previous: new ApplicationUninstallBlockedByPublicEndpointException,
            ),
        );

        $operation->refresh();

        self::assertSame(
            ApplicationOperationStatus::Failed,
            $operation->status,
        );

        self::assertSame(
            'active_public_endpoint',
            $operation->failure_code,
        );
    }

    public function test_failure_callback_does_not_overwrite_a_succeeded_operation(): void
    {
        $operation = $this->createOperation();

        self::assertTrue(
            $operation->markRunning(),
        );

        $operation->markSucceeded();

        (new RunApplicationOperationJob(
            (int) $operation->getKey(),
        ))->failed(
            new RuntimeException('late queue callback'),
        );

        $operation->refresh();

        self::assertSame(
            ApplicationOperationStatus::Succeeded,
            $operation->status,
        );

        self::assertNull(
            $operation->failure_code,
        );
    }

    private function createOperation(): ApplicationOperation
    {
        $user = User::query()->create([
            'name' => 'Operation Lifecycle Test',
            'phone' => fake()->unique()->numerify('0912#######'),
        ]);

        $server = $user->servers()->create([
            'name' => 'Operation Lifecycle Server',
            'host' => fake()->unique()->ipv4(),
            'port' => 22,
            'username' => 'root',
        ]);

        return ApplicationOperation::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n,
            'operation' => ApplicationOperationType::Install,
            'status' => ApplicationOperationStatus::Pending,
        ]);
    }
}
