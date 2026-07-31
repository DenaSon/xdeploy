<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Infrastructure\SSH\Exceptions\SSHConnectionUnavailableException;
use App\Infrastructure\SSH\Services\SSHConnectionCircuitBreaker;
use App\Models\Server;
use Livewire\Attributes\On;
use Mary\Traits\Toast;
use Throwable;

trait HandlesSshAvailability
{
    use Toast;

    public bool $sshUnavailable = false;

    public string $sshErrorMessage =
        'ارتباط SSH با سرور برقرار نشد.';

    public ?int $sshRetryAfter = null;

    /**
     * Receive SSH availability events dispatched by child components.
     */
    #[On('ssh-unavailable')]
    public function handleSshUnavailableEvent(
        string $message = 'ارتباط SSH با سرور برقرار نشد.',
        ?int $retryAfter = null,
    ): void {
        $this->markSshUnavailable(
            message: $message,
            retryAfter: $retryAfter,
            notify: true,
        );
    }

    /**
     * Move the component into the persistent SSH unavailable state.
     */
    protected function markSshUnavailable(
        string $message = 'ارتباط SSH با سرور برقرار نشد.',
        ?int $retryAfter = null,
        bool $notify = false,
    ): void {
        $shouldNotify = $notify
            && ! $this->sshUnavailable;

        $this->sshUnavailable = true;
        $this->sshErrorMessage = $this->normalizeSshMessage(
            $message,
        );
        $this->sshRetryAfter = $this->normalizeRetryAfter(
            $retryAfter,
        );

        if (! $shouldNotify) {
            return;
        }

        $this->error(
            'ارتباط با سرور قطع شد',
            $this->sshErrorMessage,
            timeout: 6_000,
        );
    }

    /**
     * Clear the persistent SSH unavailable state after a verified success.
     */
    protected function clearSshUnavailable(
        bool $notify = false,
        string $description = 'ارتباط SSH با سرور دوباره برقرار شد.',
    ): void {
        $shouldNotify = $notify
            && $this->sshUnavailable;

        $this->sshUnavailable = false;
        $this->sshErrorMessage =
            'ارتباط SSH با سرور برقرار نشد.';
        $this->sshRetryAfter = null;

        if (! $shouldNotify) {
            return;
        }

        $this->success(
            'ارتباط با سرور برقرار شد',
            $description,
            timeout: 4_000,
        );
    }

    /**
     * Convert an exception into the SSH unavailable UI state when the
     * exception, or the open circuit, proves an SSH availability failure.
     *
     * Returns true when the exception was handled as an SSH failure.
     */
    protected function handleSshFailure(
        Throwable $exception,
        SSHConnectionCircuitBreaker $circuitBreaker,
        ?Server $server,
        string $message = 'ارتباط SSH با سرور برقرار نشد.',
        bool $notify = false,
        bool $requireOpenCircuit = false,
    ): bool {
        if (
            $exception
            instanceof SSHConnectionUnavailableException
        ) {
            $this->markSshUnavailable(
                message: 'تلاش‌های اتصال به سرور موقتاً متوقف شده‌اند.',
                retryAfter: $exception->retryAfterSeconds(),
                notify: $notify,
            );

            return true;
        }

        if ($exception instanceof SSHConnectionException) {
            if (
                $requireOpenCircuit
                && ! $this->isSshCircuitOpen(
                    circuitBreaker: $circuitBreaker,
                    server: $server,
                )
            ) {
                return false;
            }

            $this->markSshUnavailable(
                message: $message,
                retryAfter: $this->resolveSshRetryAfter(
                    circuitBreaker: $circuitBreaker,
                    server: $server,
                ),
                notify: $notify,
            );

            return true;
        }

        /*
         * A Domain or Application exception may wrap an underlying SSH
         * failure. Only classify a generic exception as an SSH failure when
         * the circuit is actually open, preventing stale failure counters
         * from hiding unrelated application errors.
         */
        if (
            ! $this->isSshCircuitOpen(
                circuitBreaker: $circuitBreaker,
                server: $server,
            )
        ) {
            return false;
        }

        $this->markSshUnavailable(
            message: $message,
            retryAfter: $this->resolveSshRetryAfter(
                circuitBreaker: $circuitBreaker,
                server: $server,
            ),
            notify: $notify,
        );

        return true;
    }

    /**
     * Detect an SSH failure signal when a lower layer returns Unknown
     * instead of throwing the original connection exception.
     */
    protected function hasSshFailureSignal(
        SSHConnectionCircuitBreaker $circuitBreaker,
        ?Server $server,
        bool $requireOpenCircuit = false,
    ): bool {
        if ($server === null) {
            return false;
        }

        if ($requireOpenCircuit) {
            return $circuitBreaker->isOpen($server);
        }

        return $circuitBreaker->failures($server) > 0;
    }

    /**
     * Reset the circuit before a user-requested connection attempt.
     *
     * This intentionally does not clear the UI error state. The alert must
     * remain visible until a real SSH operation succeeds.
     */
    protected function resetSshCircuit(
        SSHConnectionCircuitBreaker $circuitBreaker,
        Server $server,
    ): void {
        $circuitBreaker->reset($server);
    }

    protected function isSshCircuitOpen(
        SSHConnectionCircuitBreaker $circuitBreaker,
        ?Server $server,
    ): bool {
        return $server !== null
            && $circuitBreaker->isOpen($server);
    }

    protected function resolveSshRetryAfter(
        SSHConnectionCircuitBreaker $circuitBreaker,
        ?Server $server,
    ): ?int {
        if (
            $server === null
            || ! $circuitBreaker->isOpen($server)
        ) {
            return null;
        }

        return max(
            1,
            $circuitBreaker->retryAfter($server),
        );
    }

    private function normalizeSshMessage(
        string $message,
    ): string {
        $message = trim($message);

        return $message !== ''
            ? $message
            : 'ارتباط SSH با سرور برقرار نشد.';
    }

    private function normalizeRetryAfter(
        ?int $retryAfter,
    ): ?int {
        if (
            $retryAfter === null
            || $retryAfter <= 0
        ) {
            return null;
        }

        return $retryAfter;
    }
}
