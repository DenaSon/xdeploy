<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Application\Applications\Actions\GetApplicationCatalogItemAction;
use App\Application\Applications\Manager\ApplicationManager;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Infrastructure\SSH\Services\SSHConnectionCircuitBreaker;
use App\Livewire\Applications\Resolvers\ApplicationManagementPanelResolver;
use App\Livewire\Concerns\HandlesSshAvailability;
use App\Models\Server;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('layouts.panel')]
final class Show extends Component
{
    use HandlesSshAvailability;

    #[Locked]
    public string $application = '';

    #[Locked]
    public string $name = '';

    #[Locked]
    public string $shortDescription = '';

    #[Locked]
    public ?string $description = null;

    #[Locked]
    public ?string $icon = null;

    #[Locked]
    public string $managementPanel = '';

    #[Locked]
    public int $serverId;

    public int $managementPanelRevision = 0;

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

    public bool $processing = false;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(
        Server $server,
        string $application,
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
        ApplicationManagementPanelResolver $managementPanelResolver,
        GetApplicationCatalogItemAction $getApplicationCatalogItem,
    ): void {
        $server = $this->resolveOwnedServer(
            $server,
        );

        $type = ApplicationType::tryFrom(
            $application,
        );

        abort_if(
            $type === null,
            404,
            'Application not found.',
        );

        $catalogItem = $getApplicationCatalogItem->execute(
            $type,
        );

        $this->serverId = (int) $server->getKey();

        $this->application = $type->value;

        $this->name = (string) $catalogItem['name'];

        $this->shortDescription = (string) (
            $catalogItem['short_description']
            ?? ''
        );

        $description = $catalogItem['description']
            ?? null;

        $this->description = is_string($description)
        && trim($description) !== ''
            ? trim($description)
            : null;

        $icon = $catalogItem['icon']
            ?? null;

        $this->icon = is_string($icon)
        && trim($icon) !== ''
            ? trim($icon)
            : null;

        $this->managementPanel =
            $managementPanelResolver->resolve(
                $type,
            );

        $this->loadApplication(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            server: $server,
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
            server: $this->server(),
            notifyOnSshFailure: true,
        );
    }

    public function retryConnection(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        $server = $this->server();

        /*
         * The persistent SSH alert remains visible until the following
         * application inspection succeeds.
         */
        $this->resetSshCircuit(
            circuitBreaker: $circuitBreaker,
            server: $server,
        );

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
                User $user,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->install(
                    user: $user,
                    server: $server,
                    type: $type,
                );
            },
            successMessage: sprintf(
                '%s با موفقیت نصب و اجرا شد.',
                $this->name,
            ),
            failureMessage: sprintf(
                'نصب %s با خطا مواجه شد.',
                $this->name,
            ),
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
                User $user,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->uninstall(
                    user: $user,
                    server: $server,
                    type: $type,
                );
            },
            successMessage: sprintf(
                '%s با موفقیت حذف شد.',
                $this->name,
            ),
            failureMessage: sprintf(
                'حذف %s با خطا مواجه شد.',
                $this->name,
            ),
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
                User $user,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->start(
                    user: $user,
                    server: $server,
                    type: $type,
                );
            },
            successMessage: sprintf(
                '%s با موفقیت اجرا شد.',
                $this->name,
            ),
            failureMessage: sprintf(
                'اجرای %s با خطا مواجه شد.',
                $this->name,
            ),
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
                User $user,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->stop(
                    user: $user,
                    server: $server,
                    type: $type,
                );
            },
            successMessage: sprintf(
                '%s با موفقیت متوقف شد.',
                $this->name,
            ),
            failureMessage: sprintf(
                'توقف %s با خطا مواجه شد.',
                $this->name,
            ),
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
                User $user,
                Server $server,
                ApplicationType $type,
            ): void {
                $manager->restart(
                    user: $user,
                    server: $server,
                    type: $type,
                );
            },
            successMessage: sprintf(
                '%s با موفقیت راه‌اندازی مجدد شد.',
                $this->name,
            ),
            failureMessage: sprintf(
                'راه‌اندازی مجدد %s با خطا مواجه شد.',
                $this->name,
            ),
        );
    }

    public function render(): View
    {
        return view(
            'livewire.applications.show',
            [
                'server' => $this->server(),
            ],
        )->title(
            $this->name,
        );
    }

    /**
     * @param Closure(
     *     ApplicationManager,
     *     User,
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

        /*
         * Resolve ownership before entering the operation try/catch.
         * A missing or foreign server must remain a 404 rather than
         * being converted into an application operation error.
         */
        $server = $this->server();
        $user = $this->authenticatedUser();

        $this->processing = true;
        $this->resetMessages();

        try {
            $operation(
                $applicationManager,
                $user,
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
                    $this->errorMessage =
                        $failureMessage;
                }

                return;
            }

            $this->successMessage =
                $successMessage;
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

            report(
                $exception,
            );

            $this->errorMessage =
                $failureMessage;
        } finally {
            $this->processing = false;
        }
    }

    private function loadApplication(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
        Server $server,
        bool $notifyOnSshFailure = false,
        bool $clearSshStateOnSuccess = true,
    ): bool {
        try {
            $info = $applicationManager->inspect(
                user: $this->authenticatedUser(),
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

            $this->setApplicationInfo(
                $info,
            );

            $this->managementPanelRevision++;

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

            report(
                $exception,
            );

            $this->info =
                $this->unknownApplicationInfo();

            $this->errorMessage =
                'دریافت وضعیت برنامه از سرور با خطا مواجه شد.';

            return false;
        }
    }

    private function prepareUnavailableApplicationState(): void
    {
        $this->info =
            $this->unknownApplicationInfo();

        $this->successMessage = null;
        $this->errorMessage = null;
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

    private function resolveOwnedServer(
        Server $server,
    ): Server {
        return $this->authenticatedUser()
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();
    }

    private function server(): Server
    {
        return $this->authenticatedUser()
            ->servers()
            ->whereKey(
                $this->serverId,
            )
            ->firstOrFail();
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
