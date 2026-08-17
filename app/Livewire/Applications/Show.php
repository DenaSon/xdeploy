<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Application\Applications\Actions\GetApplicationCatalogItemAction;
use App\Application\Applications\Manager\ApplicationManager;
use App\Application\Applications\Operations\QueueApplicationOperationAction;
use App\Application\Server\Operations\Exceptions\ServerMutationInProgressException;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Infrastructure\SSH\Services\SSHConnectionCircuitBreaker;
use App\Livewire\Applications\Resolvers\ApplicationManagementPanelResolver;
use App\Livewire\Concerns\HandlesSshAvailability;
use App\Models\ApplicationOperation;
use App\Models\Server;
use App\Models\User;
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

    #[Locked]
    public ?int $operationId = null;

    public ?string $operationType = null;

    public ?string $operationStatus = null;

    public ?string $operationStage = null;

    public ?int $operationStartedAt = null;

    public ?int $operationStageUpdatedAt = null;

    public bool $operationActive = false;

    public bool $runtimeLoaded = false;

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
        ApplicationManagementPanelResolver $managementPanelResolver,
        GetApplicationCatalogItemAction $getApplicationCatalogItem,
    ): void {
        $server = $this->resolveOwnedServer($server);

        $type = ApplicationType::tryFrom($application);

        abort_if(
            $type === null,
            404,
            'Application not found.',
        );

        $catalogItem = $getApplicationCatalogItem->execute($type);

        $this->serverId = (int) $server->getKey();
        $this->application = $type->value;
        $this->name = (string) $catalogItem['name'];
        $this->shortDescription = (string) (
            $catalogItem['short_description']
            ?? ''
        );

        $description = $catalogItem['description'] ?? null;

        $this->description = is_string($description)
        && trim($description) !== ''
            ? trim($description)
            : null;

        $icon = $catalogItem['icon'] ?? null;

        $this->icon = is_string($icon)
        && trim($icon) !== ''
            ? trim($icon)
            : null;

        $this->managementPanel = $managementPanelResolver->resolve($type);

        /*
         * Keep the first page response local and fast. Existing background
         * mutations are recovered from persistence immediately, while the
         * SSH-backed runtime inspection is triggered after first paint by
         * wire:init.
         */
        $this->syncActiveOperation();

        if ($this->operationActive) {
            $this->processing = true;
        }
    }

    public function loadRuntime(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        if (
            $this->runtimeLoaded
            || $this->operationActive
        ) {
            return;
        }

        $this->processing = true;

        try {
            $this->loadApplication(
                applicationManager: $applicationManager,
                circuitBreaker: $circuitBreaker,
                server: $this->server(),
            );
        } finally {
            $this->runtimeLoaded = true;
            $this->processing = false;
        }
    }

    public function refreshApplication(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        if ($this->operationActive) {
            return;
        }

        $this->resetMessages();

        $this->loadApplication(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            server: $this->server(),
            notifyOnSshFailure: true,
        );

        $this->runtimeLoaded = true;
    }

    public function retryConnection(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        if ($this->operationActive) {
            return;
        }

        $server = $this->server();

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

        $this->runtimeLoaded = true;

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
        QueueApplicationOperationAction $queueOperation,
    ): void {
        $this->queueBackgroundOperation(
            queueOperation: $queueOperation,
            operationType: ApplicationOperationType::Install,
            failureMessage: sprintf(
                'شروع نصب %s با خطا مواجه شد.',
                $this->name,
            ),
        );
    }

    public function uninstall(
        QueueApplicationOperationAction $queueOperation,
    ): void {
        $this->queueBackgroundOperation(
            queueOperation: $queueOperation,
            operationType: ApplicationOperationType::Uninstall,
            failureMessage: sprintf(
                'شروع حذف %s با خطا مواجه شد.',
                $this->name,
            ),
        );
    }

    public function pollOperation(
        ApplicationManager $applicationManager,
        SSHConnectionCircuitBreaker $circuitBreaker,
    ): void {
        if (
            ! $this->operationActive
            || $this->operationId === null
        ) {
            return;
        }

        $operation = ApplicationOperation::query()
            ->whereKey($this->operationId)
            ->where('user_id', $this->authenticatedUser()->getKey())
            ->where('server_id', $this->serverId)
            ->where('application_type', $this->application)
            ->first();

        if (! $operation instanceof ApplicationOperation) {
            $this->clearOperationState();
            $this->processing = false;
            $this->errorMessage = 'وضعیت عملیات پس‌زمینه در دسترس نیست.';

            return;
        }

        $this->setOperationState($operation);

        if ($operation->isActive()) {
            return;
        }

        $operationType = $operation->operation;
        $operationStatus = $operation->status;
        $operationFailureCode = is_string($operation->failure_code)
            ? $operation->failure_code
            : null;

        $this->clearOperationState();
        $this->processing = false;

        /*
         * The remote mutation is over now, so a single runtime inspection is
         * safe and gives the user the verified final application state.
         */
        $loaded = $this->loadApplication(
            applicationManager: $applicationManager,
            circuitBreaker: $circuitBreaker,
            server: $this->server(),
            notifyOnSshFailure: true,
        );

        $this->runtimeLoaded = true;

        if ($operationStatus === ApplicationOperationStatus::Succeeded) {
            if ($loaded) {
                $this->successMessage = match ($operationType) {
                    ApplicationOperationType::Install => sprintf(
                        '%s با موفقیت نصب و اجرا شد.',
                        $this->name,
                    ),
                    ApplicationOperationType::Uninstall => sprintf(
                        '%s با موفقیت حذف شد.',
                        $this->name,
                    ),
                    ApplicationOperationType::Start => sprintf(
                        '%s با موفقیت اجرا شد.',
                        $this->name,
                    ),
                    ApplicationOperationType::Stop => sprintf(
                        '%s با موفقیت متوقف شد.',
                        $this->name,
                    ),
                    ApplicationOperationType::Restart => sprintf(
                        '%s با موفقیت راه‌اندازی مجدد شد.',
                        $this->name,
                    ),
                };
            }

            return;
        }

        if (! $this->sshUnavailable) {
            $this->errorMessage = match (true) {
                $operationType === ApplicationOperationType::Install
                    && $operationFailureCode === 'package_manager_busy'
                        => 'بروزرسانی‌های اولیه سیستم‌عامل هنوز در حال اجرا هستند و مدیر بسته‌ها در زمان مجاز آزاد نشد. چند دقیقه بعد دوباره نصب را اجرا کنید.',

                default => match ($operationType) {
                    ApplicationOperationType::Install => sprintf(
                        'نصب %s با خطا مواجه شد.',
                        $this->name,
                    ),
                    ApplicationOperationType::Uninstall => sprintf(
                        'حذف %s با خطا مواجه شد.',
                        $this->name,
                    ),
                    ApplicationOperationType::Start => sprintf(
                        'اجرای %s با خطا مواجه شد.',
                        $this->name,
                    ),
                    ApplicationOperationType::Stop => sprintf(
                        'توقف %s با خطا مواجه شد.',
                        $this->name,
                    ),
                    ApplicationOperationType::Restart => sprintf(
                        'راه‌اندازی مجدد %s با خطا مواجه شد.',
                        $this->name,
                    ),
                },
            };
        }
    }

    public function start(
        QueueApplicationOperationAction $queueOperation,
    ): void {
        $this->queueBackgroundOperation(
            queueOperation: $queueOperation,
            operationType: ApplicationOperationType::Start,
            failureMessage: sprintf(
                'شروع اجرای %s با خطا مواجه شد.',
                $this->name,
            ),
        );
    }

    public function stop(
        QueueApplicationOperationAction $queueOperation,
    ): void {
        $this->queueBackgroundOperation(
            queueOperation: $queueOperation,
            operationType: ApplicationOperationType::Stop,
            failureMessage: sprintf(
                'شروع توقف %s با خطا مواجه شد.',
                $this->name,
            ),
        );
    }

    public function restart(
        QueueApplicationOperationAction $queueOperation,
    ): void {
        $this->queueBackgroundOperation(
            queueOperation: $queueOperation,
            operationType: ApplicationOperationType::Restart,
            failureMessage: sprintf(
                'شروع راه‌اندازی مجدد %s با خطا مواجه شد.',
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
        )->title($this->name);
    }

    private function queueBackgroundOperation(
        QueueApplicationOperationAction $queueOperation,
        ApplicationOperationType $operationType,
        string $failureMessage,
    ): void {
        if (
            $this->processing
            || $this->operationActive
            || $this->sshUnavailable
        ) {
            return;
        }

        $server = $this->server();
        $user = $this->authenticatedUser();

        $this->processing = true;
        $this->resetMessages();

        try {
            $operation = $queueOperation->execute(
                user: $user,
                server: $server,
                applicationType: $this->applicationType(),
                operationType: $operationType,
            );

            $this->setOperationState($operation);
        } catch (ServerMutationInProgressException) {
            $this->syncActiveOperation();

            if (! $this->operationActive) {
                $this->processing = false;
                $this->errorMessage = 'یک عملیات دیگر روی این سرور در حال انجام است. پس از پایان آن دوباره تلاش کنید.';
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->processing = false;
            $this->errorMessage = $failureMessage;
        }
    }

    private function syncActiveOperation(): void
    {
        $operation = ApplicationOperation::query()
            ->where('user_id', $this->authenticatedUser()->getKey())
            ->where('server_id', $this->serverId)
            ->where('application_type', $this->application)
            ->active()
            ->latest('id')
            ->first();

        if (! $operation instanceof ApplicationOperation) {
            $this->clearOperationState();

            return;
        }

        $this->setOperationState($operation);
    }

    private function setOperationState(
        ApplicationOperation $operation,
    ): void {
        $this->operationId = (int) $operation->getKey();
        $this->operationType = $operation->operation->value;
        $this->operationStatus = $operation->status->value;
        $this->operationStage = $operation->stage?->value;
        $this->operationStartedAt = $operation->started_at?->getTimestamp();
        $this->operationStageUpdatedAt = $operation->stage_updated_at?->getTimestamp();
        $this->operationActive = $operation->isActive();
        $this->processing = $this->operationActive;
    }

    private function clearOperationState(): void
    {
        $this->operationId = null;
        $this->operationType = null;
        $this->operationStatus = null;
        $this->operationStage = null;
        $this->operationStartedAt = null;
        $this->operationStageUpdatedAt = null;
        $this->operationActive = false;
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

            report($exception);

            $this->info = $this->unknownApplicationInfo();
            $this->errorMessage = 'دریافت وضعیت برنامه از سرور با خطا مواجه شد.';

            return false;
        }
    }

    private function prepareUnavailableApplicationState(): void
    {
        $this->info = $this->unknownApplicationInfo();
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
        return ApplicationType::from($this->application);
    }

    private function resolveOwnedServer(
        Server $server,
    ): Server {
        return $this->authenticatedUser()
            ->servers()
            ->whereKey($server->getKey())
            ->firstOrFail();
    }

    private function server(): Server
    {
        return $this->authenticatedUser()
            ->servers()
            ->whereKey($this->serverId)
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
