<?php

declare(strict_types=1);

namespace App\Application\Applications\Operations;

use App\Application\Server\Operations\ServerMutationGuard;
use App\Domain\Application\Shared\Enums\ApplicationOperationStage;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\ApplicationOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class QueueApplicationOperationAction
{
    public function __construct(
        private ServerMutationGuard $serverMutationGuard,
    ) {}

    public function execute(
        User $user,
        Server $server,
        ApplicationType $applicationType,
        ApplicationOperationType $operationType,
    ): ApplicationOperation {
        $operation = DB::transaction(
            function () use (
                $user,
                $server,
                $applicationType,
                $operationType,
            ): ApplicationOperation {
                /*
                 * Every queued runtime mutation locks the same server row.
                 * The shared guard can then inspect both operation ledgers
                 * without a cross-table enqueue race.
                 */
                $ownedServer = Server::query()
                    ->ownedBy($user)
                    ->whereKey($server->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->serverMutationGuard->ensureAvailable(
                    $ownedServer,
                );

                return ApplicationOperation::query()->create([
                    'user_id' => $user->getKey(),
                    'server_id' => $ownedServer->getKey(),
                    'application_type' => $applicationType,
                    'operation' => $operationType,
                    'status' => ApplicationOperationStatus::Pending,
                    'stage' => $operationType === ApplicationOperationType::Install
                        ? ApplicationOperationStage::Queued
                        : null,
                    'stage_updated_at' => $operationType === ApplicationOperationType::Install
                        ? now()
                        : null,
                ]);
            },
            attempts: 3,
        );

        Context::add([
            'operation_id' => (int) $operation->getKey(),
            'server_id' => (int) $operation->server_id,
            'application' => $operation->application_type->value,
            'operation' => $operation->operation->value,
        ]);

        try {
            RunApplicationOperationJob::dispatch(
                (int) $operation->getKey(),
            )->onQueue('provisioning');
        } catch (Throwable $exception) {
            $operation->markFailed(
                failureCode: 'dispatch_failed',
                failureMessage: 'The application operation could not be queued.',
            );

            throw $exception;
        }

        return $operation;
    }
}
