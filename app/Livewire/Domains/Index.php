<?php

declare(strict_types=1);

namespace App\Livewire\Domains;

use App\Application\PublicEndpoint\Contracts\PublicEndpointDriverInterface;
use App\Application\PublicEndpoint\DTOs\PublicEndpointApplicationStatus;
use App\Application\PublicEndpoint\Operations\QueuePublicEndpointOperationAction;
use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
use App\Application\Server\Operations\Exceptions\ServerMutationInProgressException;
use App\Application\Server\Operations\ServerMutationGuard;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationStatus;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationType;
use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;
use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Models\PublicEndpoint;
use App\Models\PublicEndpointOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

#[Layout('layouts.panel')]
final class Index extends Component
{
    #[Locked]
    public int $serverId;

    #[Locked]
    public ?int $removalOperationId = null;

    #[Locked]
    public ?int $removalEndpointId = null;

    public bool $removalOperationActive = false;

    /** @var array<string, array<string, mixed>> */
    public array $statuses = [];

    /** @var array<string, array{type:string,name:string,description:string,icon:string}> */
    public array $applications = [];

    public bool $loaded = false;

    public bool $unavailable = false;

    public bool $showDrawer = false;

    public bool $showSetup = false;

    public ?string $selectedApplication = null;

    public ?string $endpointError = null;

    public function mount(Server $server): void
    {
        $server = Server::query()
            ->ownedBy($this->authenticatedUser())
            ->whereKey($server->getKey())
            ->firstOrFail();

        $this->serverId = (int) $server->getKey();

        $this->cleanupSucceededRemovals();
        $this->syncActiveRemovalOperation();
    }

    public function loadDomains(PublicEndpointDriverRegistry $drivers): void
    {
        $this->loadStatuses($drivers);
    }

    public function refreshDomains(PublicEndpointDriverRegistry $drivers): void
    {
        $this->loadStatuses($drivers);
    }

    public function openDomainDrawer(): void
    {
        if (! $this->loaded || $this->unavailable) {
            return;
        }

        $this->endpointError = null;
        $this->showSetup = false;
        $this->selectedApplication = null;
        $this->showDrawer = true;

        $available = $this->availableApplications();

        if (count($available) === 1) {
            $this->selectedApplication = $available[0]['type'];
        }
    }

    public function selectApplication(string $application): void
    {
        $type = ApplicationType::tryFrom($application);

        if ($type === null || ! $this->applicationCanReceiveEndpoint($type)) {
            return;
        }

        $this->selectedApplication = $type->value;
        $this->showSetup = false;
        $this->endpointError = null;
    }

    public function continueDomainSetup(): void
    {
        $type = $this->selectedApplicationType();

        if ($type === null || ! $this->applicationCanReceiveEndpoint($type)) {
            $this->endpointError = 'یک برنامه آماده را برای اتصال دامنه انتخاب کنید.';

            return;
        }

        $this->endpointError = null;
        $this->showSetup = true;
    }

    public function manageEndpoint(int $endpointId): void
    {
        $endpoint = $this->endpoint($endpointId);
        $this->selectedApplication = $endpoint->application_type->value;
        $this->endpointError = null;
        $this->showSetup = ! $endpoint->isActive();
        $this->showDrawer = true;
    }

    public function cancelPendingEndpoint(
        int $endpointId,
        ServerMutationGuard $serverMutationGuard,
    ): void {
        try {
            $cancelled = DB::transaction(
                function () use (
                    $endpointId,
                    $serverMutationGuard,
                ): bool {
                    /*
                     * Cancellation follows the same server-first lock order
                     * as queued mutations. This prevents deleting an endpoint
                     * while its remote enable operation is pending or running.
                     */
                    $server = Server::query()
                        ->ownedBy($this->authenticatedUser())
                        ->whereKey($this->serverId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $endpoint = PublicEndpoint::query()
                        ->where('server_id', $server->getKey())
                        ->whereKey($endpointId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($endpoint->isActive()) {
                        return false;
                    }

                    $serverMutationGuard->ensureAvailable(
                        $server,
                    );

                    return (bool) $endpoint->delete();
                },
                attempts: 3,
            );
        } catch (ServerMutationInProgressException) {
            $this->endpointError =
                'یک عملیات دیگر روی این سرور در حال انجام است. پس از پایان آن دوباره تلاش کنید.';

            return;
        }

        if (! $cancelled) {
            return;
        }

        $this->selectedApplication = null;
        $this->showSetup = false;
        $this->showDrawer = false;
        $this->endpointError = null;
    }

    public function removeEndpoint(
        int $endpointId,
        QueuePublicEndpointOperationAction $queueOperation,
    ): void {
        $endpoint = $this->endpoint($endpointId);

        if (! $endpoint->isActive()) {
            return;
        }

        $this->endpointError = null;

        try {
            $operation = $queueOperation->execute(
                user: $this->authenticatedUser(),
                server: $this->server(),
                endpoint: $endpoint,
                operationType: PublicEndpointOperationType::Disable,
            );

            $this->removalOperationId = (int) $operation->getKey();
            $this->removalEndpointId = (int) $endpoint->getKey();
            $this->removalOperationActive = true;
        } catch (ServerMutationInProgressException) {
            $this->syncActiveRemovalOperation();
            $this->endpointError =
                'یک عملیات دیگر روی این سرور در حال انجام است. پس از پایان آن دوباره تلاش کنید.';
        } catch (Throwable $exception) {
            report($exception);
            $this->endpointError =
                'شروع حذف دامنه با خطا مواجه شد. دوباره تلاش کنید.';
        }
    }

    public function pollRemovalOperation(): void
    {
        if (
            ! $this->removalOperationActive
            || $this->removalOperationId === null
        ) {
            return;
        }

        $operation = PublicEndpointOperation::query()
            ->whereKey($this->removalOperationId)
            ->where('user_id', $this->authenticatedUser()->getKey())
            ->where('server_id', $this->serverId)
            ->where('operation', PublicEndpointOperationType::Disable->value)
            ->first();

        if (! $operation instanceof PublicEndpointOperation) {
            $this->clearRemovalOperation();
            $this->endpointError =
                'وضعیت عملیات حذف دامنه در دسترس نیست. صفحه را بروزرسانی کنید.';

            return;
        }

        if ($operation->isActive()) {
            return;
        }

        $this->clearRemovalOperation();

        if ($operation->status === PublicEndpointOperationStatus::Failed) {
            $this->endpointError = $this->removeOperationFailureMessage(
                $operation->failure_code,
            );

            return;
        }

        $applicationType = $operation->application_type;
        $endpoint = PublicEndpoint::query()
            ->whereKey($operation->public_endpoint_id)
            ->where('server_id', $this->serverId)
            ->first();

        if ($endpoint?->isActive() === true) {
            $this->endpointError =
                'حذف دامنه کامل نشد. وضعیت دامنه و سرور را بروزرسانی کنید.';

            return;
        }

        $endpoint?->delete();

        $this->markEndpointDisabled($applicationType);
        $this->selectedApplication = null;
        $this->showSetup = false;
        $this->showDrawer = false;
        $this->endpointError = null;
    }

    #[On('public-endpoints-updated.{serverId}')]
    public function endpointUpdated(): void
    {
        // Re-render database state without opening another SSH connection.
    }

    /** @param array<string, mixed> $status */
    #[On('public-endpoint-status-updated.{serverId}')]
    public function updateEndpointStatus(
        string $application,
        array $status,
        ?string $openUrl = null,
    ): void {
        $type = ApplicationType::tryFrom($application);
        $applicationState = data_get($status, 'application.state');
        $endpointState = data_get($status, 'endpoint.state');

        if (
            $type === null
            || ! isset($this->applications[$type->value])
            || ! is_string($applicationState)
            || ApplicationState::tryFrom($applicationState) === null
            || ! is_string($endpointState)
            || PublicEndpointRuntimeState::tryFrom($endpointState) === null
        ) {
            return;
        }

        if (is_string($openUrl) && str_starts_with($openUrl, 'https://')) {
            data_set($status, 'endpoint.open_url', $openUrl);
        }

        $this->statuses[$type->value] = $status;
        $this->loaded = true;
        $this->unavailable = false;
        $this->reconcileRuntimeEndpoint($type, $status);

        if ($endpointState === PublicEndpointRuntimeState::Enabled->value) {
            $this->showDrawer = false;
            $this->showSetup = false;
            $this->selectedApplication = null;
        }
    }

    public function render(): View
    {
        return view('livewire.domains.index', [
            'server' => $this->server(),
            'endpoints' => $this->endpointPresentation(),
            'availableApplications' => $this->availableApplications(),
            'canAddDomain' => $this->canAddDomain(),
            'selectedEndpoint' => $this->selectedEndpoint(),
            'selectedApplicationMeta' => $this->selectedApplicationMeta(),
            'hasInstalledApplications' => $this->hasInstalledApplications(),
        ])->title('دامنه‌ها');
    }

    private function loadStatuses(PublicEndpointDriverRegistry $drivers): void
    {
        $this->unavailable = false;
        $this->statuses = [];
        $this->applications = [];
        $this->endpointError = null;
        $successful = 0;
        $serverUnavailable = false;

        foreach ($drivers->all() as $driver) {
            $type = $driver->type();
            $this->applications[$type->value] = [
                'type' => $type->value,
                'name' => $driver->name(),
                'description' => $driver->description(),
                'icon' => $driver->icon(),
            ];

            if ($serverUnavailable) {
                $this->statuses[$type->value] = ['unavailable' => true];

                continue;
            }

            try {
                $status = $driver->status(
                    user: $this->authenticatedUser(),
                    server: $this->server(),
                );
                $presented = $this->presentStatus($driver, $status);
                $this->statuses[$type->value] = $presented;
                $this->reconcileRuntimeEndpoint($type, $presented);
                $successful++;
            } catch (SSHConnectionException $exception) {
                report($exception);
                $this->statuses[$type->value] = ['unavailable' => true];
                $serverUnavailable = true;
            } catch (Throwable $exception) {
                report($exception);
                $this->statuses[$type->value] = ['unavailable' => true];
            }
        }

        $this->unavailable = $successful === 0;
        $this->loaded = true;
    }

    /** @return array<string, mixed> */
    private function presentStatus(
        PublicEndpointDriverInterface $driver,
        PublicEndpointApplicationStatus $status,
    ): array {
        $presented = $status->toArray();
        $domain = $status->endpoint->domain;

        if ($status->endpoint->state === PublicEndpointRuntimeState::Enabled && is_string($domain)) {
            try {
                data_set(
                    $presented,
                    'endpoint.open_url',
                    $driver->openUrl(PublicEndpointDomain::from($domain)),
                );
            } catch (InvalidPublicEndpointDomainException) {
                // Invalid remote state remains visible as a mismatch.
            }
        }

        return $presented;
    }

    /** @param array<string, mixed> $status */
    private function reconcileRuntimeEndpoint(ApplicationType $type, array $status): void
    {
        $state = data_get($status, 'endpoint.state');
        $domain = data_get($status, 'endpoint.domain');

        if ($state !== PublicEndpointRuntimeState::Enabled->value || ! is_string($domain) || trim($domain) === '') {
            return;
        }

        try {
            $normalized = PublicEndpointDomain::from($domain)->value;
            $endpoint = PublicEndpoint::query()->firstOrNew([
                'server_id' => $this->serverId,
                'application_type' => $type->value,
            ]);
            $endpoint->domain = $normalized;
            $endpoint->activated_at ??= now();
            $endpoint->save();
        } catch (InvalidPublicEndpointDomainException|QueryException $exception) {
            report($exception);
            $this->endpointError = 'یک دامنه فعال روی سرور با وضعیت ثبت‌شده xDeploy همگام نشد. پیش از تغییر HTTPS وضعیت دامنه‌ها را بررسی کنید.';
        }
    }

    /** @return list<array<string, mixed>> */
    private function endpointPresentation(): array
    {
        return $this->endpoints()
            ->map(function (PublicEndpoint $endpoint): array {
                $type = $endpoint->application_type;
                $state = $endpoint->isActive()
                    ? $this->activeEndpointState($endpoint)
                    : 'pending';

                return [
                    'id' => (int) $endpoint->getKey(),
                    'application_type' => $type->value,
                    'application_name' => $this->applications[$type->value]['name'] ?? $type->value,
                    'domain' => $endpoint->domain,
                    'state' => $state,
                    'open_url' => $state === 'enabled'
                        ? data_get($this->statuses, "{$type->value}.endpoint.open_url")
                        : null,
                    'application_url' => route('panel.servers.applications.show', [
                        'server' => $this->serverId,
                        'application' => $type->value,
                    ]),
                    'active' => $endpoint->isActive(),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{type:string,name:string,description:string,icon:string}> */
    private function availableApplications(): array
    {
        if (! $this->loaded || $this->unavailable) {
            return [];
        }

        $available = [];

        foreach ($this->applications as $application) {
            $type = ApplicationType::tryFrom($application['type']);

            if ($type !== null && $this->applicationCanReceiveEndpoint($type)) {
                $available[] = $application;
            }
        }

        return $available;
    }

    private function applicationCanReceiveEndpoint(ApplicationType $type): bool
    {
        if (
            ! isset($this->applications[$type->value])
            || data_get($this->statuses, "{$type->value}.application.is_installed", false) !== true
        ) {
            return false;
        }

        return ! PublicEndpoint::query()
            ->where('server_id', $this->serverId)
            ->where('application_type', $type->value)
            ->exists();
    }

    private function canAddDomain(): bool
    {
        return $this->availableApplications() !== [];
    }

    private function hasInstalledApplications(): bool
    {
        foreach ($this->applications as $application) {
            if (data_get($this->statuses, "{$application['type']}.application.is_installed", false) === true) {
                return true;
            }
        }

        return false;
    }

    private function selectedApplicationType(): ?ApplicationType
    {
        return $this->selectedApplication === null
            ? null
            : ApplicationType::tryFrom($this->selectedApplication);
    }

    /** @return array<string, string>|null */
    private function selectedApplicationMeta(): ?array
    {
        $type = $this->selectedApplicationType();

        return $type === null
            ? null
            : ($this->applications[$type->value] ?? null);
    }

    private function selectedEndpoint(): ?PublicEndpoint
    {
        $type = $this->selectedApplicationType();

        if ($type === null) {
            return null;
        }

        return PublicEndpoint::query()
            ->where('server_id', $this->serverId)
            ->where('application_type', $type->value)
            ->first();
    }

    /** @return Collection<int, PublicEndpoint> */
    private function endpoints(): Collection
    {
        return PublicEndpoint::query()
            ->where('server_id', $this->serverId)
            ->orderBy('id')
            ->get();
    }

    private function endpoint(int $endpointId): PublicEndpoint
    {
        return PublicEndpoint::query()
            ->where('server_id', $this->serverId)
            ->findOrFail($endpointId);
    }

    private function activeEndpointState(PublicEndpoint $endpoint): string
    {
        $status = $this->statuses[$endpoint->application_type->value] ?? null;

        if (! is_array($status)) {
            return 'unknown';
        }

        $runtimeState = data_get($status, 'endpoint.state');
        $runtimeDomain = data_get($status, 'endpoint.domain');

        if (
            $runtimeState === PublicEndpointRuntimeState::Enabled->value
            && is_string($runtimeDomain)
            && strtolower(trim($runtimeDomain)) === $endpoint->domain
        ) {
            return 'enabled';
        }

        return $runtimeState === PublicEndpointRuntimeState::Unknown->value
            ? 'unknown'
            : 'misconfigured';
    }

    private function cleanupSucceededRemovals(): void
    {
        $operations = PublicEndpointOperation::query()
            ->where('user_id', $this->authenticatedUser()->getKey())
            ->where('server_id', $this->serverId)
            ->where('operation', PublicEndpointOperationType::Disable->value)
            ->where('status', PublicEndpointOperationStatus::Succeeded->value)
            ->get();

        foreach ($operations as $operation) {
            $endpoint = PublicEndpoint::query()
                ->whereKey($operation->public_endpoint_id)
                ->where('server_id', $this->serverId)
                ->first();

            if ($endpoint !== null && ! $endpoint->isActive()) {
                $endpoint->delete();
            }
        }
    }

    private function syncActiveRemovalOperation(): void
    {
        $operation = PublicEndpointOperation::query()
            ->where('user_id', $this->authenticatedUser()->getKey())
            ->where('server_id', $this->serverId)
            ->where('operation', PublicEndpointOperationType::Disable->value)
            ->active()
            ->latest('id')
            ->first();

        if (! $operation instanceof PublicEndpointOperation) {
            $this->clearRemovalOperation();

            return;
        }

        $this->removalOperationId = (int) $operation->getKey();
        $this->removalEndpointId = (int) $operation->public_endpoint_id;
        $this->removalOperationActive = true;
    }

    private function clearRemovalOperation(): void
    {
        $this->removalOperationId = null;
        $this->removalEndpointId = null;
        $this->removalOperationActive = false;
    }

    private function markEndpointDisabled(ApplicationType $type): void
    {
        if (! isset($this->statuses[$type->value])) {
            return;
        }

        data_set(
            $this->statuses,
            "{$type->value}.endpoint.state",
            PublicEndpointRuntimeState::Disabled->value,
        );

        data_set(
            $this->statuses,
            "{$type->value}.endpoint.domain",
            null,
        );

        data_set(
            $this->statuses,
            "{$type->value}.endpoint.open_url",
            null,
        );
    }

    private function removeOperationFailureMessage(?string $failureCode): string
    {
        $failure = $failureCode === null
            ? null
            : PublicEndpointOperationFailure::tryFrom($failureCode);

        return match ($failure) {
            PublicEndpointOperationFailure::ExistingConfiguration => 'پیکربندی دامنه روی سرور با endpoint ثبت‌شده هم‌خوان نیست. برای جلوگیری از حذف تنظیمات ناشناخته عملیات متوقف شد.',
            PublicEndpointOperationFailure::OperationInProgress => 'یک عملیات دامنه دیگر روی سرور در حال اجرا است. پس از پایان آن دوباره تلاش کنید.',
            PublicEndpointOperationFailure::EnvironmentUnavailable => 'ابزارها یا فایل‌های لازم برای حذف امن دامنه در دسترس نیستند.',
            PublicEndpointOperationFailure::CandidateValidation,
            PublicEndpointOperationFailure::Mutation,
            PublicEndpointOperationFailure::Verification,
            PublicEndpointOperationFailure::Preflight => 'حذف دامنه کامل نشد. وضعیت برنامه و Caddy را بروزرسانی و دوباره بررسی کنید.',
            null => 'حذف دامنه با خطا مواجه شد. وضعیت سرور را بروزرسانی و دوباره تلاش کنید.',
        };
    }

    private function server(): Server
    {
        return Server::query()
            ->ownedBy($this->authenticatedUser())
            ->findOrFail($this->serverId);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
