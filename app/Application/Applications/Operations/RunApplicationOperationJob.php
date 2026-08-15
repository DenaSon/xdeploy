<?php

declare(strict_types=1);

namespace App\Application\Applications\Operations;

use App\Application\Applications\Manager\ApplicationManager;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Exceptions\ApplicationInstallationException;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Models\ApplicationOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RunApplicationOperationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /**
     * Docker provisioning may consume up to 30 minutes by itself. Leave
     * enough room for the application installer and final verification.
     */
    public int $timeout = 2700;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $operationId,
    ) {}

    public function handle(
        ApplicationManager $applicationManager,
        SSHConnectionInterface $ssh,
    ): void {
        $operation = ApplicationOperation::query()->find(
            $this->operationId,
        );

        if (
            ! $operation instanceof ApplicationOperation
            || ! $operation->markRunning()
        ) {
            return;
        }

        $startedAt = microtime(true);

        Log::info('application.operation.started', [
            'status' => 'running',
        ]);

        try {
            $user = User::query()->findOrFail(
                $operation->user_id,
            );

            $server = Server::query()->findOrFail(
                $operation->server_id,
            );

            $progressReporter = $operation->operation === ApplicationOperationType::Install
                ? new DatabaseApplicationOperationProgressReporter(
                    (int) $operation->getKey(),
                )
                : null;

            match ($operation->operation) {
                ApplicationOperationType::Install => $applicationManager->install(
                    user: $user,
                    server: $server,
                    type: $operation->application_type,
                    progressReporter: $progressReporter,
                ),

                ApplicationOperationType::Uninstall => $applicationManager->uninstall(
                    user: $user,
                    server: $server,
                    type: $operation->application_type,
                ),

                ApplicationOperationType::Start => $applicationManager->start(
                    user: $user,
                    server: $server,
                    type: $operation->application_type,
                ),

                ApplicationOperationType::Stop => $applicationManager->stop(
                    user: $user,
                    server: $server,
                    type: $operation->application_type,
                ),

                ApplicationOperationType::Restart => $applicationManager->restart(
                    user: $user,
                    server: $server,
                    type: $operation->application_type,
                ),
            };

            $operation->refresh();
            $operation->markSucceeded();

            Log::info('application.operation.completed', [
                'status' => 'succeeded',
                'duration_ms' => $this->durationInMilliseconds(
                    $startedAt,
                ),
            ]);
        } catch (Throwable $exception) {
            $failureCode = $this->failureCode(
                $exception,
            );

            $operation->refresh();
            $operation->markFailed(
                failureCode: $failureCode,
                failureMessage: 'The queued application operation failed.',
            );

            Log::error('application.operation.completed', [
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
                Log::warning('application.operation.ssh_cleanup_failed', [
                    'operation_id' => (int) $operation->getKey(),
                    'exception_type' => $exception::class,
                ]);
            }
        }
    }

    public function failed(
        ?Throwable $exception,
    ): void {
        $operation = ApplicationOperation::query()->find(
            $this->operationId,
        );

        if (
            ! $operation instanceof ApplicationOperation
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
            failureMessage: 'The application operation job did not complete.',
        );

        Log::error('application.operation.completed', [
            'status' => 'failed',
            'failure_code' => $failureCode,
            'exception_type' => $exception === null
                ? null
                : $exception::class,
            'job_failure_callback' => true,
        ]);
    }

    private function failureCode(
        Throwable $exception,
        string $fallback = 'operation_failed',
    ): string {
        if (
            $exception instanceof ApplicationInstallationException
            && $exception->failureCode !== null
        ) {
            return $exception->failureCode;
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
