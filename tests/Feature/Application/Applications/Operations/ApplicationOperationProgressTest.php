<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Applications\Operations;

use App\Application\Applications\Operations\DatabaseApplicationOperationProgressReporter;
use App\Application\Applications\Operations\QueueApplicationOperationAction;
use App\Domain\Application\Shared\Enums\ApplicationOperationStage;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\ApplicationOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ApplicationOperationProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_operation_is_queued_with_an_initial_progress_stage(): void
    {
        Queue::fake();

        $user = $this->createUser();
        $server = $this->createServer($user);

        $operation = app(
            QueueApplicationOperationAction::class,
        )->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        self::assertSame(
            ApplicationOperationStage::Queued,
            $operation->stage,
        );

        self::assertNotNull(
            $operation->stage_updated_at,
        );
    }

    public function test_progress_reporter_advances_only_active_operations_and_success_completes_the_stage(): void
    {
        $user = $this->createUser();
        $server = $this->createServer($user);

        $operation = ApplicationOperation::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n,
            'operation' => ApplicationOperationType::Install,
            'status' => ApplicationOperationStatus::Pending,
            'stage' => ApplicationOperationStage::Queued,
            'stage_updated_at' => now(),
        ]);

        self::assertTrue(
            $operation->markRunning(),
        );

        $reporter = new DatabaseApplicationOperationProgressReporter(
            (int) $operation->getKey(),
        );

        $reporter->report(
            ApplicationOperationStage::InstallingDependencies,
        );

        $operation->refresh();

        self::assertSame(
            ApplicationOperationStage::InstallingDependencies,
            $operation->stage,
        );

        self::assertNotNull(
            $operation->stage_updated_at,
        );

        $operation->markSucceeded();
        $operation->refresh();

        self::assertSame(
            ApplicationOperationStatus::Succeeded,
            $operation->status,
        );

        self::assertSame(
            ApplicationOperationStage::Completed,
            $operation->stage,
        );

        $reporter->report(
            ApplicationOperationStage::StartingApplication,
        );

        $operation->refresh();

        self::assertSame(
            ApplicationOperationStage::Completed,
            $operation->stage,
        );
    }

    private function createUser(): User
    {
        return User::query()->create([
            'phone' => fake()->unique()->numerify('0912#######'),
        ]);
    }

    private function createServer(User $user): Server
    {
        return $user->servers()->create([
            'name' => 'Progress Test Server',
            'host' => fake()->unique()->ipv4(),
            'port' => 22,
            'username' => 'root',
        ]);
    }
}
