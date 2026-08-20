<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Applications;

use App\Application\Applications\Operations\RunApplicationOperationJob;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationStatus;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Applications\Show;
use App\Models\ApplicationCatalogItem;
use App\Models\ApplicationOperation;
use App\Models\PublicEndpoint;
use App\Models\PublicEndpointOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ApplicationLifecycleQueueTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('lifecycleOperationProvider')]
    public function test_lifecycle_mutations_are_queued(
        string $method,
        ApplicationOperationType $operationType,
    ): void {
        Queue::fake();

        $user = $this->createUser('09120000051');
        $server = $this->createServer($user, '192.0.2.51');

        $this->createCatalogItem();

        Livewire::actingAs($user)
            ->test(Show::class, [
                'server' => $server,
                'application' => ApplicationType::N8n->value,
            ])
            ->call($method)
            ->assertSet('operationType', $operationType->value)
            ->assertSet(
                'operationStatus',
                ApplicationOperationStatus::Pending->value,
            )
            ->assertSet('operationActive', true)
            ->assertSet('processing', true);

        $operation = ApplicationOperation::query()->sole();

        self::assertSame($operationType, $operation->operation);

        Queue::assertPushed(
            RunApplicationOperationJob::class,
            static fn (RunApplicationOperationJob $job): bool => $job->operationId === $operation->getKey(),
        );
    }

    public function test_endpoint_operation_blocks_a_lifecycle_mutation_with_a_clear_message(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000052');
        $server = $this->createServer($user, '192.0.2.52');
        $this->createCatalogItem();

        $endpoint = PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => 'panel.example.com',
        ]);

        PublicEndpointOperation::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'public_endpoint_id' => $endpoint->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => $endpoint->domain,
            'operation' => PublicEndpointOperationType::Enable,
            'status' => PublicEndpointOperationStatus::Running,
        ]);

        Livewire::actingAs($user)
            ->test(Show::class, [
                'server' => $server,
                'application' => ApplicationType::N8n->value,
            ])
            ->call('start')
            ->assertSet('operationActive', false)
            ->assertSet('processing', false)
            ->assertSet(
                'errorMessage',
                'یک عملیات دیگر روی این سرور در حال انجام است. پس از پایان آن دوباره تلاش کنید.',
            );

        $this->assertDatabaseCount('application_operations', 0);
        Queue::assertNotPushed(RunApplicationOperationJob::class);
    }

    public function test_active_endpoint_blocks_uninstall_with_a_clear_message(): void
    {
        Queue::fake();

        $user = $this->createUser('09120000053');
        $server = $this->createServer($user, '192.0.2.53');
        $this->createCatalogItem();

        PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n,
            'domain' => 'automation.example.com',
            'activated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Show::class, [
                'server' => $server,
                'application' => ApplicationType::N8n->value,
            ])
            ->call('uninstall')
            ->assertSet('operationActive', false)
            ->assertSet('processing', false)
            ->assertSet(
                'errorMessage',
                'ابتدا دامنه این برنامه را غیرفعال یا درخواست اتصال آن را لغو کنید، سپس برنامه را حذف کنید.',
            );

        $this->assertDatabaseCount('application_operations', 0);
        Queue::assertNotPushed(RunApplicationOperationJob::class);
    }

    /**
     * @return iterable<string, array{string, ApplicationOperationType}>
     */
    public static function lifecycleOperationProvider(): iterable
    {
        yield 'start' => [
            'start',
            ApplicationOperationType::Start,
        ];

        yield 'stop' => [
            'stop',
            ApplicationOperationType::Stop,
        ];

        yield 'restart' => [
            'restart',
            ApplicationOperationType::Restart,
        ];
    }

    private function createCatalogItem(): ApplicationCatalogItem
    {
        return ApplicationCatalogItem::query()->create([
            'slug' => ApplicationType::N8n->value,
            'name' => 'n8n',
            'short_description' => 'Workflow automation',
            'description' => 'Automation platform',
            'icon' => 'lucide.workflow',
            'is_published' => true,
            'sort_order' => 10,
        ]);
    }

    private function createUser(string $phone): User
    {
        return User::query()->create([
            'name' => 'Application Lifecycle Queue Test',
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        string $host,
    ): Server {
        $server = new Server([
            'name' => 'Application Lifecycle Queue Server',
            'host' => $host,
            'port' => 22,
            'username' => 'root',
        ]);

        $server->status = ServerStatus::Active;
        $user->servers()->save($server);

        return $server->refresh();
    }
}
