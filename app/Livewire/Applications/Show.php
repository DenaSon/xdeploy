<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Application\Applications\Manager\ApplicationManager;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Infrastructure\SSH\Services\SSHConnectionCircuitBreaker;
use App\Livewire\Concerns\HandlesSshAvailability;
use App\Models\Server;
use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.panel')]
final class Show extends Component
{
    use HandlesSshAvailability;

    public string $application = '';

    public string $name = '';

    /**
     * @var array{
     *     state: string,
     *     version: string|null,
     *     is_installed: bool,
     *     is_running: bool,
     *     is_not_installed: bool,
     *     is_unknown: bool
     * }
     */
    public array $info = [
        'state' => 'unknown',
        'version' => null,
        'is_installed' => false,
        'is_running' => false,
        'is_not_installed' => false,
        'is_unknown' => true,
    ];

    public bool $serverMissing = false;

    public bool $processing = false;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(
        string $application,
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $type = ApplicationType::tryFrom($application);

        abort_if(
            $type === null,
            404,
            'Application not found.',
        );

        $this->application = $type->value;
        $this->name = ucfirst($type->value);

        $this->loadApplication(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
        );
    }

    public function refreshApplication(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $this->resetMessages();

        $this->loadApplication(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
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
         * The persistent SSH alert remains visible until the following
         * application inspection succeeds.
         */
        $this->resetSshCircuit(
            circuitBreaker: $circuitBreaker,
            server: $server,
        );

        $this->serverMissing = false;
        $this->resetMessages();

        $loaded = $this->loadApplication(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            server: $server,
            notifyOnSshFailure: false,
            clearSshStateOnSuccess: false,
        );

        if ($loaded) {
            $this->clearSshUnavailable(
                notify: true,
                description: 'وضعیت برنامه دوباره دریافت شد.',
            );

            return;
        }

        if ($this->sshUnavailable) {
            $this->error(
                'اتصال برقرار نشد',
                'وضعیت سرور، پورت SSH و اطلاعات ورود را بررسی کنید.',
            );

            return;
        }

        $this->error(
            'دریافت وضعیت ناموفق بود',
            $this->errorMessage
            ?? 'دریافت وضعیت برنامه با خطا مواجه شد.',
        );
    }

    public function install(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $this->performOperation(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            operation: static function (
                ApplicationManager $manager,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->install(
                    server: $server,
                    type: $type,
                );
            },
            successMessage: 'مرزبان با موفقیت نصب و اجرا شد.',
            failureMessage: 'نصب مرزبان با خطا مواجه شد.',
        );
    }

    public function uninstall(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $this->performOperation(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            operation: static function (
                ApplicationManager $manager,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->uninstall(
                    server: $server,
                    type: $type,
                );
            },
            successMessage: 'مرزبان با موفقیت حذف شد.',
            failureMessage: 'حذف مرزبان با خطا مواجه شد.',
        );
    }

    public function start(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $this->performOperation(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            operation: static function (
                ApplicationManager $manager,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->start(
                    server: $server,
                    type: $type,
                );
            },
            successMessage: 'مرزبان با موفقیت اجرا شد.',
            failureMessage: 'اجرای مرزبان با خطا مواجه شد.',
        );
    }

    public function stop(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $this->performOperation(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            operation: static function (
                ApplicationManager $manager,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->stop(
                    server: $server,
                    type: $type,
                );
            },
            successMessage: 'مرزبان با موفقیت متوقف شد.',
            failureMessage: 'توقف مرزبان با خطا مواجه شد.',
        );
    }

    public function restart(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $this->performOperation(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            operation: static function (
                ApplicationManager $manager,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->restart(
                    server: $server,
                    type: $type,
                );
            },
            successMessage: 'مرزبان با موفقیت راه‌اندازی مجدد شد.',
            failureMessage: 'راه‌اندازی مجدد مرزبان با خطا مواجه شد.',
        );
    }

    public function render(): View
    {
        return view('livewire.applications.show')
            ->title($this->name);
    }

    /**
     * @param Closure(
     *     ApplicationManager,
     *     Server,
     *     ApplicationType
     * ): void $operation
     */
    private function performOperation(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
        Closure $operation,
        string $successMessage,
        string $failureMessage,
    ): void {
        if (
            $this->processing
            || $this->sshUnavailable
        ) {
            return;
        }

        $this->processing = true;
        $this->resetMessages();

        $server = null;

        try {
            $server = $this->activeServer();

            if ($server === null) {
                $this->handleMissingServer();

                return;
            }

            $this->serverMissing = false;

            $operation(
                $applicationManager,
                $server,
                $this->applicationType(),
            );

            /*
             * Do not report operation success until the final application
             * state has been fetched and verified successfully.
             */
            $loaded = $this->loadApplication(
                applicationManager: $applicationManager,
                circuitBreaker: $circuitBreaker,
                server: $server,
                notifyOnSshFailure: true,
            );

            if (! $loaded) {
                if (
                    ! $this->sshUnavailable
                    && $this->errorMessage === null
                ) {
                    $this->errorMessage = $failureMessage;
                }

                return;
            }

            $this->successMessage = $successMessage;
        } catch (Throwable $exception) {
            if (
                $this->handleSshFailure(
                    exception: $exception,
                    circuitBreaker: $circuitBreaker,
                    server: $server,
                    message: 'ارتباط SSH هنگام اجرای عملیات برنامه قطع شد.',
                    notify: true,
                )
            ) {
                $this->prepareUnavailableApplicationState();

                return;
            }

            report($exception);

            $this->errorMessage = $failureMessage;
        } finally {
            $this->processing = false;
        }
    }

    private function loadApplication(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
        ?Server $server = null,
        bool $notifyOnSshFailure = false,
        bool $clearSshStateOnSuccess = true,
    ): bool {
        $server ??= $this->activeServer();

        if ($server === null) {
            $this->handleMissingServer();

            return false;
        }

        try {
            $this->serverMissing = false;

            $info = $applicationManager->inspect(
                server: $server,
                type: $this->applicationType(),
            );

            /*
             * Some application inspectors convert infrastructure exceptions
             * into an Unknown application state. The circuit failure counter
             * allows the UI to recognize the underlying SSH problem.
             */
            if (
                $info->isUnknown()
                && $this->hasSshFailureSignal(
                    circuitBreaker: $circuitBreaker,
                    server: $server,
                )
            ) {
                $this->prepareUnavailableApplicationState();

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

            $this->setApplicationInfo($info);

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
                    notify: $notifyOnSshFailure,
                )
            ) {
                $this->prepareUnavailableApplicationState();

                return false;
            }

            report($exception);

            $this->info = $this->unknownApplicationInfo();
            $this->errorMessage =
                'دریافت وضعیت برنامه از سرور با خطا مواجه شد.';

            return false;
        }
    }

    private function prepareUnavailableApplicationState(): void
    {
        $this->info = $this->unknownApplicationInfo();
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    private function handleMissingServer(): void
    {
        $this->serverMissing = true;
        $this->info = $this->unknownApplicationInfo();
        $this->successMessage = null;
        $this->errorMessage =
            'هیچ سرور فعالی وجود ندارد.';

        $this->clearSshUnavailable();
    }

    private function setApplicationInfo(
        ApplicationInfo $info,
    ): void {
        $this->info = [
            'state' => $info->state->value,
            'version' => $info->version(),
            'is_installed' => $info->isInstalled(),
            'is_running' => $info->isRunning(),
            'is_not_installed' => $info->isNotInstalled(),
            'is_unknown' => $info->isUnknown(),
        ];
    }

    private function applicationType(): ApplicationType
    {
        return ApplicationType::from(
            $this->application,
        );
    }

    private function activeServer(): ?Server
    {
        return Server::query()
            ->active()
            ->first();
    }

    private function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    /**
     * @return array{
     *     state: string,
     *     version: null,
     *     is_installed: false,
     *     is_running: false,
     *     is_not_installed: false,
     *     is_unknown: true
     * }
     */
    private function unknownApplicationInfo(): array
    {
        return [
            'state' => 'unknown',
            'version' => null,
            'is_installed' => false,
            'is_running' => false,
            'is_not_installed' => false,
            'is_unknown' => true,
        ];
    }
}
