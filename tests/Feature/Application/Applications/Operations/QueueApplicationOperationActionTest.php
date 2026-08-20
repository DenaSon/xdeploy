<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Applications\Operations;

use App\Application\Applications\Operations\Exceptions\ApplicationUninstallBlockedByPublicEndpointException;
use App\Application\Applications\Operations\QueueApplicationOperationAction;
use App\Application\Applications\Operations\RunApplicationOperationJob;
use App\Application\Server\Operations\Exceptions\ServerMutationInProgressException;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationStatus;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationType;
use App\Models\ApplicationOperation;
use App\Models\PublicEndpoint;
use App\Models\PublicEndpointOperation;
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

    public function test_it_rejects_a_second_active_operation_for_the_same_server(): void
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
            ServerMutationInProgressException::class,
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

    public function test_active_public_endpoint_blocks_application_uninstall(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000009');
        $server = $this->createServer($user, '192.0.2.20');
        $endpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::N8n,
            domain: 'automation.example.com',
        );

        $endpoint->forceFill([
            'activated_at' => now(),
        ])->save();

        $this->expectException(
            ApplicationUninstallBlockedByPublicEndpointException::class,
        );

        try {
            app(QueueApplicationOperationAction::class)->execute(
                user: $user,
                server: $server,
                applicationType: ApplicationType::N8n,
                operationType: ApplicationOperationType::Uninstall,
            );
        } finally {
            $this->assertDatabaseCount(
                'application_operations',
                0,
            );

            Queue::assertNotPushed(
                RunApplicationOperationJob::class,
            );
        }
    }

    public function test_pending_public_endpoint_blocks_application_uninstall(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000010');
        $server = $this->createServer($user, '192.0.2.21');

        $this->createEndpoint(
            server: $server,
            type: ApplicationType::N8n,
            domain: 'pending.example.com',
        );

        $this->expectException(
            ApplicationUninstallBlockedByPublicEndpointException::class,
        );

        app(QueueApplicationOperationAction::class)->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Uninstall,
        );
    }

    public function test_disabled_public_endpoint_allows_application_uninstall(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000011');
        $server = $this->createServer($user, '192.0.2.22');
        $endpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::N8n,
            domain: 'disabled.example.com',
        );

        $endpoint->forceFill([
            'disabled_at' => now(),
        ])->save();

        $operation = app(
            QueueApplicationOperationAction::class,
        )->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Uninstall,
        );

        self::assertSame(
            ApplicationOperationStatus::Pending,
            $operation->status,
        );

        Queue::assertPushed(
            RunApplicationOperationJob::class,
        );
    }

    public function test_active_endpoint_does_not_block_non_uninstall_lifecycle_operations(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000012');
        $server = $this->createServer($user, '192.0.2.23');
        $endpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::N8n,
            domain: 'active.example.com',
        );

        $endpoint->forceFill([
            'activated_at' => now(),
        ])->save();

        $operation = app(
            QueueApplicationOperationAction::class,
        )->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Restart,
        );

        self::assertSame(
            ApplicationOperationStatus::Pending,
            $operation->status,
        );
    }

    public function test_active_endpoint_for_another_application_does_not_block_uninstall(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000013');
        $server = $this->createServer($user, '192.0.2.24');
        $endpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::Marzban,
            domain: 'panel.example.com',
        );

        $endpoint->forceFill([
            'activated_at' => now(),
        ])->save();

        $operation = app(
            QueueApplicationOperationAction::class,
        )->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Uninstall,
        );

        self::assertSame(
            ApplicationOperationStatus::Pending,
            $operation->status,
        );
    }

    public function test_an_active_operation_blocks_a_different_application_on_the_same_server(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000006');
        $server = $this->createServer($user, '192.0.2.16');
        $action = app(QueueApplicationOperationAction::class);

        $action->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        $this->expectException(
            ServerMutationInProgressException::class,
        );

        $action->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::Marzban,
            operationType: ApplicationOperationType::Install,
        );
    }

    public function test_an_active_public_endpoint_operation_blocks_an_application_operation(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000007');
        $server = $this->createServer($user, '192.0.2.17');
        $endpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::Marzban,
            domain: 'panel.example.com',
        );

        PublicEndpointOperation::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'public_endpoint_id' => $endpoint->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => $endpoint->domain,
            'operation' => PublicEndpointOperationType::Enable,
            'status' => PublicEndpointOperationStatus::Running,
        ]);

        $this->expectException(
            ServerMutationInProgressException::class,
        );

        app(QueueApplicationOperationAction::class)->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );
    }

    public function test_active_operations_on_another_server_do_not_block_the_target_server(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000008');
        $busyServer = $this->createServer($user, '192.0.2.18');
        $availableServer = $this->createServer($user, '192.0.2.19');
        $action = app(QueueApplicationOperationAction::class);

        $action->execute(
            user: $user,
            server: $busyServer,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        $operation = $action->execute(
            user: $user,
            server: $availableServer,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        self::assertSame(
            $availableServer->getKey(),
            $operation->server_id,
        );
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

    private function createEndpoint(
        Server $server,
        ApplicationType $type,
        string $domain,
    ): PublicEndpoint {
        return PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => $type,
            'domain' => $domain,
        ]);
    }
}
