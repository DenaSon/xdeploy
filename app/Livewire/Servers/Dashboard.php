<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\ServerManager;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\SSHConnectionCircuitBreaker;
use App\Livewire\Concerns\HandlesSshAvailability;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.panel')]
#[Title('Dashboard')]
final class Dashboard extends Component
{
    use HandlesSshAvailability;

    public Server $server;

    /**
     * Single server snapshot used by every Dashboard section.
     *
     * @var array<string, mixed>
     */
    public array $overview = [];

    public ?string $errorMessage = null;

    public function mount(
        Server $server,
        SSHConnectionInterface $ssh,
        SSHConnectionCircuitBreaker $circuitBreaker,
        ServerManager $serverManager,
    ): void {
        $this->server = $server;

        /*
         * ServerManager may return cached overview data. Therefore, an
         * explicit non-cached SSH availability check is performed first.
         */
        if (
            ! $this->verifySshConnection(
                ssh: $ssh,
                circuitBreaker: $circuitBreaker,
            )
        ) {
            return;
        }

        $this->loadOverview(
            serverManager: $serverManager,
            circuitBreaker: $circuitBreaker,
        );
    }

    /**
     * Lightweight and non-cached SSH heartbeat.
     *
     * The first failed attempt is only recorded. The Dashboard enters the
     * unavailable state after the circuit breaker becomes open.
     */
    public function checkConnection(
        SSHConnectionInterface $ssh,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $this->verifySshConnection(
            ssh: $ssh,
            circuitBreaker: $circuitBreaker,
            message: 'ارتباط SSH با سرور قطع شده است و تلاش‌های خودکار موقتاً متوقف شدند.',
            notifyOnFailure: true,
            requireOpenCircuit: true,
        );
    }

    /**
     * Reset the circuit, verify the real SSH connection and then reload the
     * complete Dashboard snapshot.
     */
    public function retryConnection(
        SSHConnectionCircuitBreaker $circuitBreaker,
        SSHConnectionInterface $ssh,
        ServerManager $serverManager,
    ): void {
        $this->resetSshCircuit(
            circuitBreaker: $circuitBreaker,
            server: $this->server,
        );

        $this->errorMessage = null;

        /*
         * The persistent alert remains visible until both the SSH check and
         * overview retrieval succeed.
         */
        if (
            ! $this->verifySshConnection(
                ssh: $ssh,
                circuitBreaker: $circuitBreaker,
                message: 'برقراری دوباره ارتباط SSH با سرور ناموفق بود.',
            )
        ) {
            $this->error(
                title: 'اتصال برقرار نشد',
                description: 'وضعیت شبکه، پورت SSH، سرویس SSH و اطلاعات ورود سرور را بررسی کنید.',
                timeout: 5_000,
            );

            return;
        }

        $loaded = $this->loadOverview(
            serverManager: $serverManager,
            circuitBreaker: $circuitBreaker,
            notifyOnSshFailure: false,
            clearSshStateOnSuccess: false,
        );

        if (! $loaded) {
            if ($this->sshUnavailable) {
                $this->error(
                    title: 'اتصال برقرار نشد',
                    description: 'دریافت اطلاعات سرور از طریق SSH ناموفق بود.',
                    timeout: 5_000,
                );

                return;
            }

            $this->error(
                title: 'دریافت اطلاعات ناموفق بود',
                description: $this->errorMessage
                ?? 'دریافت اطلاعات داشبورد با خطا مواجه شد.',
                timeout: 5_000,
            );

            return;
        }

        $this->clearSshUnavailable(
            notify: true,
            description: 'اطلاعات داشبورد دوباره دریافت شدند.',
        );
    }

    public function render(): View
    {
        return view('livewire.servers.dashboard');
    }

    /**
     * Retrieve one complete snapshot for every Dashboard section.
     */
    private function loadOverview(
        ServerManager $serverManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
        bool $notifyOnSshFailure = false,
        bool $clearSshStateOnSuccess = true,
    ): bool {
        try {
            $overview = $serverManager
                ->overview($this->server)
                ->toArray();

            $this->overview = $overview;
            $this->errorMessage = null;

            if ($clearSshStateOnSuccess) {
                $this->clearSshUnavailable();
            }

            return true;
        } catch (Throwable $exception) {
            if (
                $this->handleSshFailure(
                    exception: $exception,
                    circuitBreaker: $circuitBreaker,
                    server: $this->server,
                    message: 'ارتباط SSH هنگام دریافت اطلاعات داشبورد قطع شد.',
                    notify: $notifyOnSshFailure,
                )
            ) {
                $this->prepareUnavailableDashboardState();

                return false;
            }

            report($exception);

            $this->overview = [];
            $this->errorMessage =
                'دریافت اطلاعات سرور با خطا مواجه شد.';

            return false;
        }
    }

    /**
     * Perform a real SSH connection attempt without using overview cache.
     *
     * When $requireOpenCircuit is true, transient failures do not hide the
     * Dashboard until the circuit breaker reaches its configured threshold.
     */
    private function verifySshConnection(
        SSHConnectionInterface $ssh,
        SSHConnectionCircuitBreaker $circuitBreaker,
        string $message = 'ارتباط SSH با سرور برقرار نشد.',
        bool $notifyOnFailure = false,
        bool $requireOpenCircuit = false,
    ): bool {
        try {
            if ($ssh->connect($this->server)) {
                return true;
            }

            if (
                $requireOpenCircuit
                && ! $this->isSshCircuitOpen(
                    circuitBreaker: $circuitBreaker,
                    server: $this->server,
                )
            ) {
                return false;
            }

            $this->prepareUnavailableDashboardState();

            $this->markSshUnavailable(
                message: $message,
                retryAfter: $this->resolveSshRetryAfter(
                    circuitBreaker: $circuitBreaker,
                    server: $this->server,
                ),
                notify: $notifyOnFailure,
            );

            return false;
        } catch (Throwable $exception) {
            if (
                $this->handleSshFailure(
                    exception: $exception,
                    circuitBreaker: $circuitBreaker,
                    server: $this->server,
                    message: $message,
                    notify: $notifyOnFailure,
                    requireOpenCircuit: $requireOpenCircuit,
                )
            ) {
                $this->prepareUnavailableDashboardState();

                return false;
            }

            /*
             * During heartbeat, an unrelated transient exception should not
             * immediately remove the Dashboard.
             */
            report($exception);

            if ($requireOpenCircuit) {
                return false;
            }

            $this->prepareUnavailableDashboardState();

            $this->markSshUnavailable(
                message: 'هنگام بررسی ارتباط SSH خطای غیرمنتظره‌ای رخ داد.',
                notify: $notifyOnFailure,
            );

            return false;
        } finally {
            $ssh->disconnect();
        }
    }

    private function prepareUnavailableDashboardState(): void
    {
        $this->overview = [];
        $this->errorMessage = null;
    }
}
