<?php

declare(strict_types=1);

namespace App\Application\PublicEndpoint\Operations;

use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationType;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Models\PublicEndpoint;
use App\Models\PublicEndpointOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class RunPublicEndpointOperationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1200;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $operationId,
    ) {}

    public function handle(
        PublicEndpointDriverRegistry $drivers,
        SSHConnectionInterface $ssh,
    ): void {
        $operation = PublicEndpointOperation::query()->find(
            $this->operationId,
        );

        if (
            ! $operation instanceof PublicEndpointOperation
            || ! $operation->markRunning()
        ) {
            return;
        }

        $startedAt = microtime(true);

        Log::info('public_endpoint.operation.started', [
            'operation_id' => (int) $operation->getKey(),
            'server_id' => (int) $operation->server_id,
            'application' => $operation->application_type->value,
            'operation' => $operation->operation->value,
            'status' => 'running',
        ]);

        try {
            $user = User::query()->findOrFail(
                $operation->user_id,
            );

            $server = Server::query()
                ->ownedBy($user)
                ->whereKey($operation->server_id)
                ->firstOrFail();

            $endpoint = $this->resolveEndpoint($operation);
            $domain = PublicEndpointDomain::from(
                $operation->domain,
            );
            $driver = $drivers->find(
                $operation->application_type,
            );

            match ($operation->operation) {
                PublicEndpointOperationType::Enable => $driver->enable(
                    user: $user,
                    server: $server,
                    domain: $domain,
                ),

                PublicEndpointOperationType::Disable => $driver->disable(
                    user: $user,
                    server: $server,
                    domain: $domain,
                ),
            };

            $this->finalizeEndpoint(
                operation: $operation,
                endpoint: $endpoint,
            );

            $operation->markSucceeded();

            Log::info('public_endpoint.operation.completed', [
                'operation_id' => (int) $operation->getKey(),
                'status' => 'succeeded',
                'duration_ms' => $this->durationInMilliseconds(
                    $startedAt,
                ),
            ]);
        } catch (Throwable $exception) {
            $failureCode = $this->failureCode($exception);

            $operation->markFailed(
                failureCode: $failureCode,
                failureMessage: 'The queued public endpoint operation failed.',
            );

            Log::error('public_endpoint.operation.completed', [
                'operation_id' => (int) $operation->getKey(),
                'status' => 'failed',
                'failure_code' => $failureCode,
                'duration_ms' => $this->durationInMilliseconds(
                    $startedAt,
                ),
                'exception_type' => $exception::class,
            ]);

            throw $exception;
        } finally {
            try {
                $ssh->disconnect();
            } catch (Throwable $exception) {
                Log::warning('public_endpoint.operation.ssh_cleanup_failed', [
                    'operation_id' => (int) $operation->getKey(),
                    'exception_type' => $exception::class,
                ]);
            }
        }
    }

    public function failed(
        ?Throwable $exception,
    ): void {
        $operation = PublicEndpointOperation::query()->find(
            $this->operationId,
        );

        if (
            ! $operation instanceof PublicEndpointOperation
            || ! $operation->isActive()
        ) {
            return;
        }

        $failureCode = $exception === null
            ? 'job_failed'
            : $this->failureCode(
                $exception,
                fallback: 'job_failed_with_exception',
            );

        $operation->markFailed(
            failureCode: $failureCode,
            failureMessage: 'The public endpoint operation job did not complete.',
        );

        Log::error('public_endpoint.operation.completed', [
            'operation_id' => (int) $operation->getKey(),
            'status' => 'failed',
            'failure_code' => $failureCode,
            'exception_type' => $exception === null
                ? null
                : $exception::class,
            'job_failure_callback' => true,
        ]);
    }

    private function resolveEndpoint(
        PublicEndpointOperation $operation,
    ): PublicEndpoint {
        $endpoint = PublicEndpoint::query()
            ->whereKey($operation->public_endpoint_id)
            ->where('server_id', $operation->server_id)
            ->where(
                'application_type',
                $operation->application_type->value,
            )
            ->where('domain', $operation->domain)
            ->first();

        if (! $endpoint instanceof PublicEndpoint) {
            throw PublicEndpointOperationException::existingConfiguration();
        }

        if (
            $operation->operation === PublicEndpointOperationType::Enable
            && $endpoint->isActive()
        ) {
            throw PublicEndpointOperationException::existingConfiguration();
        }

        return $endpoint;
    }

    private function finalizeEndpoint(
        PublicEndpointOperation $operation,
        PublicEndpoint $endpoint,
    ): void {
        DB::transaction(function () use ($operation, $endpoint): void {
            $lockedEndpoint = PublicEndpoint::query()
                ->whereKey($endpoint->getKey())
                ->where('server_id', $operation->server_id)
                ->where(
                    'application_type',
                    $operation->application_type->value,
                )
                ->where('domain', $operation->domain)
                ->lockForUpdate()
                ->first();

            if (! $lockedEndpoint instanceof PublicEndpoint) {
                throw new RuntimeException(
                    'The public endpoint changed while its remote operation was running.',
                );
            }

            $lockedEndpoint->activated_at = match ($operation->operation) {
                PublicEndpointOperationType::Enable => now(),
                PublicEndpointOperationType::Disable => null,
            };

            $lockedEndpoint->save();
        });
    }

    private function failureCode(
        Throwable $exception,
        string $fallback = 'operation_failed',
    ): string {
        if ($exception instanceof PublicEndpointOperationException) {
            return $exception->failure->value;
        }

        if ($exception instanceof SSHConnectionException) {
            return PublicEndpointOperationFailure::EnvironmentUnavailable->value;
        }

        return $fallback;
    }

    private function durationInMilliseconds(float $startedAt): int
    {
        return (int) round(
            (microtime(true) - $startedAt) * 1000,
        );
    }
}
