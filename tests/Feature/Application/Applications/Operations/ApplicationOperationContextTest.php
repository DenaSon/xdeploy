<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Applications\Operations;

use App\Application\Applications\Operations\QueueApplicationOperationAction;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ApplicationOperationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_queued_operation_adds_correlation_context_for_logs_and_the_job(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'name' => 'Operation Context Test',
            'phone' => '09120000091',
        ]);

        $server = $user->servers()->create([
            'name' => 'Operation Context Server',
            'host' => '192.0.2.91',
            'port' => 22,
            'username' => 'root',
        ]);

        $operation = app(
            QueueApplicationOperationAction::class,
        )->execute(
            user: $user,
            server: $server,
            applicationType: ApplicationType::N8n,
            operationType: ApplicationOperationType::Install,
        );

        self::assertSame(
            (int) $operation->getKey(),
            Context::get('operation_id'),
        );
        self::assertSame(
            (int) $server->getKey(),
            Context::get('server_id'),
        );
        self::assertSame(
            ApplicationType::N8n->value,
            Context::get('application'),
        );
        self::assertSame(
            ApplicationOperationType::Install->value,
            Context::get('operation'),
        );
    }
}
