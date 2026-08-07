<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Application\Applications\Manager\ApplicationManager;
use App\Infrastructure\SSH\Services\SSHConnectionCircuitBreaker;
use App\Livewire\Concerns\HandlesSshAvailability;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.panel')]
final class Index extends Component
{
    use HandlesSshAvailability;

    /**
     * @var array<int, array{
     *     type: string,
     *     name: string,
     *     state: string,
     *     version: string|null,
     *     is_installed: bool,
     *     is_running: bool,
     *     is_unknown: bool,
     * }>
     */
    public array $applications = [];

    public bool $serverMissing = false;

    public ?string $errorMessage = null;

    public function mount(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $server = $this->activeServer();

        if ($server === null) {
            $this->handleMissingServer();

            return;
        }

        $this->loadApplications(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            server: $server,
        );
    }

    public function refreshApplications(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $server = $this->activeServer();

        if ($server === null) {
            $this->handleMissingServer();

            return;
        }

        $this->serverMissing = false;
        $this->errorMessage = null;

        $this->loadApplications(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            server: $server,
            notifyOnSshFailure: true,
        );
    }

    public function retryConnection(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $server = $this->activeServer();

        if ($server === null) {
            $this->handleMissingServer();

            return;
        }

        /*
         * فقط Circuit ریست می‌شود.
         * Alert تا زمان موفقیت واقعی اتصال باقی می‌ماند.
         */
        $this->resetSshCircuit(
            circuitBreaker: $circuitBreaker,
            server: $server,
        );

        $this->serverMissing = false;
        $this->errorMessage = null;

        $loaded = $this->loadApplications(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            server: $server,
            notifyOnSshFailure: false,
            clearSshStateOnSuccess: false,
        );

        if ($loaded) {
            $this->clearSshUnavailable(
                notify: true,
                description: 'وضعیت برنامه‌ها دوباره دریافت شد.',
            );

            return;
        }

        if ($this->sshUnavailable) {
            $this->error(
                title: 'اتصال برقرار نشد',
                description: 'وضعیت سرور، پورت SSH و اطلاعات ورود را بررسی کنید.',
                timeout: 5_000,
            );

            return;
        }

        $this->error(
            title: 'دریافت اطلاعات ناموفق بود',
            description: $this->errorMessage
            ?? 'دریافت وضعیت برنامه‌ها با خطا مواجه شد.',
            timeout: 5_000,
        );
    }

    public function render(): View
    {
        return view('livewire.applications.index');
    }

    private function loadApplications(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
        Server $server,
        bool $notifyOnSshFailure = false,
        bool $clearSshStateOnSuccess = true,
    ): bool {
        try {
            $overview = $applicationManager->overview(
                user: $this->authenticatedUser(),
                server: $server,
            );

            $applications = array_map(
                static fn (array $application): array => [
                    'type' => $application['type']->value,
                    'name' => $application['name'],
                    'state' => $application['info']->state->value,
                    'version' => $application['info']->version(),
                    'is_installed' => $application['info']->isInstalled(),
                    'is_running' => $application['info']->isRunning(),
                    'is_unknown' => $application['info']->isUnknown(),
                ],
                $overview,
            );

            /*
             * بعضی Inspectorها خطای زیرساخت را به وضعیت Unknown
             * تبدیل می‌کنند؛ در این حالت Circuit سیگنال قطعی را می‌دهد.
             */
            if (
                $this->allApplicationsUnknown($applications)
                && $this->hasSshFailureSignal(
                    circuitBreaker: $circuitBreaker,
                    server: $server,
                )
            ) {
                $this->prepareUnavailableApplicationsState();

                $this->markSshUnavailable(
                    message: 'ارتباط SSH با سرور برقرار نشد.',
                    retryAfter: $this->resolveSshRetryAfter(
                        circuitBreaker: $circuitBreaker,
                        server: $server,
                    ),
                    notify: $notifyOnSshFailure,
                );

                return false;
            }

            $this->applications = $applications;
            $this->serverMissing = false;
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
                    server: $server,
                    message: 'ارتباط SSH با سرور برقرار نشد.',
                    notify: $notifyOnSshFailure,
                )
            ) {
                $this->prepareUnavailableApplicationsState();

                return false;
            }

            report($exception);

            $this->applications = [];
            $this->errorMessage =
                'دریافت وضعیت برنامه‌های سرور با خطا مواجه شد.';

            return false;
        }
    }

    /**
     * @param array<int, array{
     *     is_unknown: bool
     * }> $applications
     */
    private function allApplicationsUnknown(
        array $applications,
    ): bool {
        if ($applications === []) {
            return false;
        }

        foreach ($applications as $application) {
            if (! $application['is_unknown']) {
                return false;
            }
        }

        return true;
    }

    private function prepareUnavailableApplicationsState(): void
    {
        $this->applications = [];
        $this->errorMessage = null;
    }

    private function handleMissingServer(): void
    {
        $this->serverMissing = true;
        $this->applications = [];
        $this->errorMessage = null;

        $this->clearSshUnavailable();
    }

    private function activeServer(): ?Server
    {
        return Server::query()
            ->activeFor(
                $this->authenticatedUser(),
            )
            ->first();
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
