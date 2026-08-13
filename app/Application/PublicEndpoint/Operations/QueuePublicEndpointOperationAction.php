<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint\Operations;

use App\Application\PublicEndpoint\Operations\Exceptions\PublicEndpointOperationInProgressException;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationStatus;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationType;
use App\Models\PublicEndpoint;
use App\Models\PublicEndpointOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class QueuePublicEndpointOperationAction
{
    public function execute(
        User $user,
        Server $server,
        PublicEndpoint $endpoint,
        PublicEndpointOperationType $operationType,
    ): PublicEndpointOperation {
        $operation = DB::transaction(
            function () use (
                $user,
                $server,
                $endpoint,
                $operationType,
            ): PublicEndpointOperation {
                $ownedServer = Server::query()
                    ->ownedBy($user)
                    ->whereKey($server->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $ownedEndpoint = PublicEndpoint::query()
                    ->where('server_id', $ownedServer->getKey())
                    ->whereKey($endpoint->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $activeOperationExists = PublicEndpointOperation::query()
                    ->where('user_id', $user->getKey())
                    ->where('server_id', $ownedServer->getKey())
                    ->where(
                        'application_type',
                        $ownedEndpoint->application_type->value,
                    )
                    ->active()
                    ->exists();

                if ($activeOperationExists) {
                    throw new PublicEndpointOperationInProgressException;
                }

                return PublicEndpointOperation::query()->create([
                    'user_id' => $user->getKey(),
                    'server_id' => $ownedServer->getKey(),
                    'public_endpoint_id' => $ownedEndpoint->getKey(),
                    'application_type' => $ownedEndpoint->application_type,
                    'domain' => $ownedEndpoint->domain,
                    'operation' => $operationType,
                    'status' => PublicEndpointOperationStatus::Pending,
                ]);
            },
            attempts: 3,
        );

        Context::add([
            'public_endpoint_operation_id' => (int) $operation->getKey(),
            'server_id' => (int) $operation->server_id,
            'application' => $operation->application_type->value,
            'domain' => $operation->domain,
            'operation' => $operation->operation->value,
        ]);

        try {
            RunPublicEndpointOperationJob::dispatch(
                (int) $operation->getKey(),
            )->onQueue('provisioning');
        } catch (Throwable $exception) {
            $operation->markFailed(
                failureCode: 'dispatch_failed',
                failureMessage: 'The public endpoint operation could not be queued.',
            );

            throw $exception;
        }

        return $operation;
    }
}
