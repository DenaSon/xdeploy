<?php

declare(strict_types=1);

namespace App\Application\Applications\Operations;

use App\Application\Applications\Operations\Exceptions\ApplicationOperationInProgressException;
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
                 * Lock the owning server row so two requests cannot create
                 * competing mutations for the same application target.
                 */
                $ownedServer = Server::query()
                    ->ownedBy($user)
                    ->whereKey($server->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $activeOperationExists = ApplicationOperation::query()
                    ->where('user_id', $user->getKey())
                    ->where('server_id', $ownedServer->getKey())
                    ->where('application_type', $applicationType->value)
                    ->active()
                    ->exists();

                if ($activeOperationExists) {
                    throw new ApplicationOperationInProgressException;
                }

                return ApplicationOperation::query()->create([
                    'user_id' => $user->getKey(),
                    'server_id' => $ownedServer->getKey(),
                    'application_type' => $applicationType,
                    'operation' => $operationType,
                    'status' => ApplicationOperationStatus::Pending,
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
