<?php

declare(strict_types=1);

namespace Tests\Feature\Application\PublicEndpoint\Operations;

use App\Application\PublicEndpoint\Operations\QueuePublicEndpointOperationAction;
use App\Application\PublicEndpoint\Operations\RunPublicEndpointOperationJob;
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

final class QueuePublicEndpointOperationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_and_dispatches_an_endpoint_operation(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000041');
        $server = $this->createServer($user, '192.0.2.41');
        $endpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::N8n,
            domain: 'automation.example.com',
        );

        $operation = app(
            QueuePublicEndpointOperationAction::class,
        )->execute(
            user: $user,
            server: $server,
            endpoint: $endpoint,
            operationType: PublicEndpointOperationType::Enable,
        );

        self::assertSame(
            PublicEndpointOperationStatus::Pending,
            $operation->status,
        );

        $this->assertDatabaseHas('public_endpoint_operations', [
            'id' => $operation->getKey(),
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'public_endpoint_id' => $endpoint->getKey(),
            'application_type' => ApplicationType::N8n->value,
            'operation' => PublicEndpointOperationType::Enable->value,
            'status' => PublicEndpointOperationStatus::Pending->value,
        ]);

        Queue::assertPushedOn(
            'provisioning',
            RunPublicEndpointOperationJob::class,
        );
    }

    public function test_an_active_application_operation_blocks_an_endpoint_operation(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000042');
        $server = $this->createServer($user, '192.0.2.42');
        $endpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::N8n,
            domain: 'automation.example.com',
        );

        ApplicationOperation::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::Marzban,
            'operation' => ApplicationOperationType::Install,
            'status' => ApplicationOperationStatus::Running,
        ]);

        $this->expectException(
            ServerMutationInProgressException::class,
        );

        app(QueuePublicEndpointOperationAction::class)->execute(
            user: $user,
            server: $server,
            endpoint: $endpoint,
            operationType: PublicEndpointOperationType::Enable,
        );
    }

    public function test_an_active_endpoint_operation_blocks_a_different_endpoint_on_the_same_server(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000043');
        $server = $this->createServer($user, '192.0.2.43');
        $marzbanEndpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::Marzban,
            domain: 'panel.example.com',
        );
        $n8nEndpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::N8n,
            domain: 'automation.example.com',
        );

        PublicEndpointOperation::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'public_endpoint_id' => $marzbanEndpoint->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => $marzbanEndpoint->domain,
            'operation' => PublicEndpointOperationType::Enable,
            'status' => PublicEndpointOperationStatus::Pending,
        ]);

        $this->expectException(
            ServerMutationInProgressException::class,
        );

        app(QueuePublicEndpointOperationAction::class)->execute(
            user: $user,
            server: $server,
            endpoint: $n8nEndpoint,
            operationType: PublicEndpointOperationType::Enable,
        );
    }

    public function test_an_active_operation_on_another_server_does_not_block_the_endpoint(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000044');
        $busyServer = $this->createServer($user, '192.0.2.44');
        $availableServer = $this->createServer($user, '192.0.2.45');

        ApplicationOperation::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $busyServer->getKey(),
            'application_type' => ApplicationType::N8n,
            'operation' => ApplicationOperationType::Install,
            'status' => ApplicationOperationStatus::Pending,
        ]);

        $endpoint = $this->createEndpoint(
            server: $availableServer,
            type: ApplicationType::N8n,
            domain: 'automation.example.com',
        );

        $operation = app(
            QueuePublicEndpointOperationAction::class,
        )->execute(
            user: $user,
            server: $availableServer,
            endpoint: $endpoint,
            operationType: PublicEndpointOperationType::Enable,
        );

        self::assertSame(
            $availableServer->getKey(),
            $operation->server_id,
        );
    }

    public function test_user_cannot_queue_an_endpoint_operation_for_another_users_server(): void
    {
        Queue::fake();

        $owner = $this->createUser('09120000046');
        $otherUser = $this->createUser('09120000047');
        $server = $this->createServer($owner, '192.0.2.46');
        $endpoint = $this->createEndpoint(
            server: $server,
            type: ApplicationType::N8n,
            domain: 'automation.example.com',
        );

        $this->expectException(
            ModelNotFoundException::class,
        );

        app(QueuePublicEndpointOperationAction::class)->execute(
            user: $otherUser,
            server: $server,
            endpoint: $endpoint,
            operationType: PublicEndpointOperationType::Enable,
        );
    }

    private function createUser(string $phone): User
    {
        return User::query()->create([
            'name' => 'Public Endpoint Operation Test',
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        string $host,
    ): Server {
        return $user->servers()->create([
            'name' => 'Public Endpoint Operation Server',
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
