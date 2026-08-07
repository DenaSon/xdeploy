<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\ActivateServerAction;
use App\Application\Server\Actions\EnsureSupportedOperatingSystemAction;
use App\Application\Server\ServerManager;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Infrastructure\Linux\Exceptions\OperatingSystemInspectionException;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Enums\SSHCommandReadinessStatus;
use App\Infrastructure\SSH\Services\SSHCommandReadinessInspector;
use App\Infrastructure\SSH\Services\SSHConnectionCircuitBreaker;
use App\Livewire\Concerns\HandlesSshAvailability;
use App\Livewire\Servers\Enums\DashboardReadinessIssue;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
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

    /**
     * Runtime readiness issue.
     *
     * Stored as a scalar string for safe Livewire hydration.
     */
    public ?string $readinessIssue = null;

    /**
     * Human-readable OS detected during the readiness check.
     */
    public ?string $readinessOperatingSystem = null;

    public function mount(
        Server $server,
        ActivateServerAction $activateServer,
        SSHConnectionInterface $ssh,
        SSHConnectionCircuitBreaker $circuitBreaker,
        SSHCommandReadinessInspector $commandReadiness,
        EnsureSupportedOperatingSystemAction $ensureSupportedOperatingSystem,
        ServerManager $serverManager,
    ): void {
        $user = Auth::user();

        abort_unless($user instanceof User, 401);

        $this->server = $activateServer->handle(
            $user,
            $server,
        );

        /*
         * ServerManager may return cached overview data. A non-cached
         * readiness check must pass before any Dashboard snapshot is used.
         */
        if (
            ! $this->verifyServerReadiness(
                ssh: $ssh,
                circuitBreaker: $circuitBreaker,
                commandReadiness: $commandReadiness,
                ensureSupportedOperatingSystem: $ensureSupportedOperatingSystem,
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
     * Lightweight runtime heartbeat.
     *
     * SSH transport failures keep the existing circuit-breaker semantics:
     * the first transient failure does not immediately hide the Dashboard.
     *
     * Deterministic readiness failures such as password-change-required or
     * unsupported OS block the Dashboard immediately.
     */
    public function checkConnection(
        SSHConnectionInterface $ssh,
        SSHConnectionCircuitBreaker $circuitBreaker,
        SSHCommandReadinessInspector $commandReadiness,
        EnsureSupportedOperatingSystemAction $ensureSupportedOperatingSystem,
    ): void {
        $this->verifyServerReadiness(
            ssh: $ssh,
            circuitBreaker: $circuitBreaker,
            commandReadiness: $commandReadiness,
            ensureSupportedOperatingSystem: $ensureSupportedOperatingSystem,
            message: 'ارتباط SSH با سرور قطع شده است و تلاش‌های خودکار موقتاً متوقف شدند.',
            notifyOnFailure: true,
            requireOpenCircuit: true,
        );
    }

    /**
     * User-requested retry.
     *
     * Refreshing the Server model is important after the user changes an
     * expired password and stores the new credential from the Edit screen.
     */
    public function retryConnection(
        SSHConnectionCircuitBreaker $circuitBreaker,
        SSHConnectionInterface $ssh,
        SSHCommandReadinessInspector $commandReadiness,
        EnsureSupportedOperatingSystemAction $ensureSupportedOperatingSystem,
        ServerManager $serverManager,
    ): void {
        $this->resetSshCircuit(
            circuitBreaker: $circuitBreaker,
            server: $this->server,
        );

        $this->server->refresh();
        $this->errorMessage = null;

        if (
            ! $this->verifyServerReadiness(
                ssh: $ssh,
                circuitBreaker: $circuitBreaker,
                commandReadiness: $commandReadiness,
                ensureSupportedOperatingSystem: $ensureSupportedOperatingSystem,
                message: 'برقراری دوباره ارتباط SSH با سرور ناموفق بود.',
            )
        ) {
            if ($this->sshUnavailable) {
                $this->error(
                    title: 'اتصال برقرار نشد',
                    description: 'وضعیت شبکه، پورت SSH، سرویس SSH و اطلاعات ورود سرور را بررسی کنید.',
                    timeout: 5_000,
                );

                return;
            }

            /*
             * Readiness issues already have a persistent, specific alert.
             * Do not replace them with a generic failure toast.
             */
            if ($this->readinessIssue !== null) {
                return;
            }

            if ($this->errorMessage !== null) {
                $this->error(
                    title: 'بررسی آمادگی ناموفق بود',
                    description: $this->errorMessage,
                    timeout: 5_000,
                );
            }

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

        $this->clearSshUnavailable();
        $this->clearReadinessIssue();

        $this->success(
            title: 'سرور آماده است',
            description: 'آمادگی سرور تأیید شد و اطلاعات داشبورد دوباره دریافت شدند.',
            timeout: 4_000,
        );
    }

    public function render(): View
    {
        return view('livewire.servers.dashboard');
    }

    /**
     * Verify transport, command execution, and OS compatibility.
     *
     * This method never changes credentials. Password rotation remains
     * exclusive to the Cloud provisioning bootstrap workflow.
     */
    private function verifyServerReadiness(
        SSHConnectionInterface $ssh,
        SSHConnectionCircuitBreaker $circuitBreaker,
        SSHCommandReadinessInspector $commandReadiness,
        EnsureSupportedOperatingSystemAction $ensureSupportedOperatingSystem,
        string $message = 'ارتباط SSH با سرور برقرار نشد.',
        bool $notifyOnFailure = false,
        bool $requireOpenCircuit = false,
    ): bool {
        try {
            if (! $ssh->connect($this->server)) {
                if (
                    $requireOpenCircuit
                    && ! $this->isSshCircuitOpen(
                        circuitBreaker: $circuitBreaker,
                        server: $this->server,
                    )
                ) {
                    return false;
                }

                $this->prepareSshUnavailableDashboardState();

                $this->markSshUnavailable(
                    message: $message,
                    retryAfter: $this->resolveSshRetryAfter(
                        circuitBreaker: $circuitBreaker,
                        server: $this->server,
                    ),
                    notify: $notifyOnFailure,
                );

                return false;
            }

            /*
             * Transport/authentication is healthy at this point.
             * Any previous SSH-unavailable state must not mask a more precise
             * command or operating-system readiness issue.
             */
            $this->clearSshUnavailable();

            $commandStatus = $commandReadiness
                ->inspect();

            if (
                $commandStatus
                === SSHCommandReadinessStatus::PasswordChangeRequired
            ) {
                $this->markReadinessIssue(
                    DashboardReadinessIssue::PasswordChangeRequired,
                );

                return false;
            }

            if (
                $commandStatus
                === SSHCommandReadinessStatus::CommandUnavailable
            ) {
                $this->markReadinessIssue(
                    DashboardReadinessIssue::CommandUnavailable,
                );

                return false;
            }

            try {
                $operatingSystem = $ensureSupportedOperatingSystem
                    ->handle();
            } catch (
                UnsupportedOperatingSystemException $exception
            ) {
                $this->markReadinessIssue(
                    issue: DashboardReadinessIssue::UnsupportedOperatingSystem,
                    operatingSystem: $exception->operatingSystem->displayName(),
                );

                return false;
            } catch (
                OperatingSystemInspectionException
            ) {
                $this->markReadinessIssue(
                    DashboardReadinessIssue::OperatingSystemInspectionFailed,
                );

                return false;
            }

            $this->readinessOperatingSystem =
                $operatingSystem->displayName();

            $this->clearReadinessIssue(
                preserveOperatingSystem: true,
            );

            return true;
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
                $this->prepareSshUnavailableDashboardState();

                return false;
            }

            /*
             * During heartbeat, an unrelated transient exception should not
             * immediately remove a previously valid Dashboard snapshot.
             */
            report($exception);

            if ($requireOpenCircuit) {
                return false;
            }

            $this->prepareGenericDashboardErrorState();

            $this->errorMessage =
                'بررسی آمادگی سرور با خطای غیرمنتظره‌ای مواجه شد.';

            return false;
        } finally {
            $ssh->disconnect();
        }
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
                $this->prepareSshUnavailableDashboardState();

                return false;
            }

            report($exception);

            $this->overview = [];
            $this->errorMessage =
                'دریافت اطلاعات سرور با خطا مواجه شد.';

            return false;
        }
    }

    private function markReadinessIssue(
        DashboardReadinessIssue $issue,
        ?string $operatingSystem = null,
    ): void {
        $this->clearSshUnavailable();

        $this->overview = [];
        $this->errorMessage = null;
        $this->readinessIssue = $issue->value;
        $this->readinessOperatingSystem =
            $operatingSystem;
    }

    private function clearReadinessIssue(
        bool $preserveOperatingSystem = false,
    ): void {
        $this->readinessIssue = null;

        if (! $preserveOperatingSystem) {
            $this->readinessOperatingSystem = null;
        }
    }

    private function prepareSshUnavailableDashboardState(): void
    {
        $this->overview = [];
        $this->errorMessage = null;
        $this->clearReadinessIssue();
    }

    private function prepareGenericDashboardErrorState(): void
    {
        $this->overview = [];
        $this->clearReadinessIssue();
    }
}
