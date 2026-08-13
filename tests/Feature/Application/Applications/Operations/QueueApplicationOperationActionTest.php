<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Applications\Operations;

use App\Application\Applications\Operations\Exceptions\ApplicationOperationInProgressException;
use App\Application\Applications\Operations\QueueApplicationOperationAction;
use App\Application\Applications\Operations\RunApplicationOperationJob;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\ApplicationOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class QueueApplicationOperationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_and_dispatches_an_install_operation(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000001');
        $server = $this->createServer($user, '192.0.2.11');

        $operation = app(
            QueueApplicationOperationAction::class,
        )->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        self::assertSame(
            ApplicationOperationStatus::Pending,
            $operation->status,
        );

        $this->assertDatabaseHas(
            'application_operations',
            [
                'id' => $operation->getKey(),
                'user_id' => $user->getKey(),
                'server_id' => $server->getKey(),
                'application_type' => ApplicationType::N8n->value,
                'operation' => ApplicationOperationType::Install->value,
                'status' => ApplicationOperationStatus::Pending->value,
            ],
        );

        Queue::assertPushedOn(
            'provisioning',
            RunApplicationOperationJob::class,
        );

        Queue::assertPushed(
            RunApplicationOperationJob::class,
            static fn (RunApplicationOperationJob $job): bool => $job->operationId === $operation->getKey(),
        );
    }

    public function test_it_rejects_a_second_active_operation_for_the_same_target(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000002');
        $server = $this->createServer($user, '192.0.2.12');
        $action = app(QueueApplicationOperationAction::class);

        $action->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        $this->expectException(
            ApplicationOperationInProgressException::class,
        );

        try {
            $action->execute(
                user: $user,
                server: $server,
                applicationType: ApplicationType::N8n,
                operationType: ApplicationOperationType::Uninstall,
            );
        } finally {
            self::assertSame(
                1,
                ApplicationOperation::query()
                    ->where('server_id', $server->getKey())
                    ->where('application_type', ApplicationType::N8n->value)
                    ->active()
                    ->count(),
            );
        }
    }

    public function test_a_new_operation_is_allowed_after_the_previous_one_is_terminal(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000003');
        $server = $this->createServer($user, '192.0.2.13');
        $action = app(QueueApplicationOperationAction::class);

        $first = $action->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        $first->markFailed(
            failureCode: 'test_failure',
        );

        $second = $action->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        self::assertNotSame(
            $first->getKey(),
            $second->getKey(),
        );

        self::assertSame(
            ApplicationOperationStatus::Pending,
            $second->status,
        );
    }

    public function test_user_cannot_queue_an_operation_for_another_users_server(): void
    {
        Queue::fake();

        $owner = $this->createUser('09120000004');
        $otherUser = $this->createUser('09120000005');
        $server = $this->createServer($owner, '192.0.2.14');

        $this->expectException(
            ModelNotFoundException::class,
        );

        app(
            QueueApplicationOperationAction::class,
        )->execute(
            user: $otherUser,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );
    }

    private function createUser(string $phone): User
    {
        return User::query()->create([
            'name' => 'Application Operation Test',
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        string $host,
    ): Server {
        return $user->servers()->create([
            'name' => 'Operation Test Server',
            'host' => $host,
            'port' => 22,
            'username' => 'root',
        ]);
    }
}
