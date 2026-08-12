<?php

declare(strict_types=1);

namespace App\Livewire\Domains;

use App\Application\Applications\Marzban\MarzbanManager;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Models\PublicEndpoint;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
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

    /**
     * @var array<string, mixed>
     */
    public array $management = [];

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
    }

    public function loadDomains(MarzbanManager $manager): void
    {
        $this->loadManagement($manager);
    }

    public function refreshDomains(MarzbanManager $manager): void
    {
        $this->loadManagement($manager);
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

    public function selectApplication(
        string $application,
    ): void {
        $type = ApplicationType::tryFrom(
            $application,
        );

        if (
            $type === null
            || ! $this->applicationCanReceiveEndpoint($type)
        ) {
            return;
        }

        $this->selectedApplication = $type->value;
        $this->showSetup = false;
        $this->endpointError = null;
    }

    public function continueDomainSetup(): void
    {
        $type = $this->selectedApplicationType();

        if (
            $type === null
            || ! $this->applicationCanReceiveEndpoint($type)
        ) {
            $this->endpointError =
                'یک برنامه آماده را برای اتصال دامنه انتخاب کنید.';

            return;
        }

        $this->endpointError = null;
        $this->showSetup = true;
    }

    public function manageEndpoint(
        int $endpointId,
    ): void {
        $endpoint = $this->endpoint(
            $endpointId,
        );

        $this->selectedApplication =
            $endpoint->application_type->value;

        $this->endpointError = null;
        $this->showSetup = ! $endpoint->isActive();
        $this->showDrawer = true;
    }

    public function cancelPendingEndpoint(
        int $endpointId,
    ): void {
        $endpoint = $this->endpoint(
            $endpointId,
        );

        if ($endpoint->isActive()) {
            return;
        }

        $endpoint->delete();

        $this->selectedApplication = null;
        $this->showSetup = false;
        $this->showDrawer = false;
        $this->endpointError = null;
    }

    public function removeEndpoint(
        int $endpointId,
        MarzbanManager $manager,
    ): void {
        $endpoint = $this->endpoint(
            $endpointId,
        );

        if (! $endpoint->isActive()) {
            return;
        }

        if ($endpoint->application_type !== ApplicationType::Marzban) {
            return;
        }

        $this->endpointError = null;

        try {
            $management = $manager->disableHttps(
                user: $this->authenticatedUser(),
                server: $this->server(),
                domain: $endpoint->domain,
            )->toArray();

            $endpoint->delete();

            $this->management = $management;
            $this->loaded = true;
            $this->unavailable = false;
            $this->selectedApplication = null;
            $this->showSetup = false;
            $this->showDrawer = false;
            $this->endpointError = null;
        } catch (MarzbanHttpsApplyException $exception) {
            report($exception);

            $this->endpointError = $this->removeErrorMessage(
                $exception,
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->endpointError =
                'حذف دامنه با خطای پیش‌بینی‌نشده متوقف شد. وضعیت سرور را بروزرسانی و دوباره بررسی کنید.';
        }
    }

    #[On('public-endpoints-updated.{serverId}')]
    public function endpointUpdated(): void
    {
        // Re-render from the database without opening a new SSH connection.
    }

    /**
     * @param  array<string, mixed>  $management
     */
    #[On('marzban-management-updated.{serverId}')]
    public function updateManagement(array $management): void
    {
        $applicationState = data_get(
            $management,
            'application.state',
        );

        $httpsState = data_get(
            $management,
            'https.state',
        );

        if (
            ! is_string($applicationState)
            || ApplicationState::tryFrom($applicationState) === null
            || ! is_string($httpsState)
            || MarzbanHttpsState::tryFrom($httpsState) === null
        ) {
            return;
        }

        $this->management = $management;
        $this->loaded = true;
        $this->unavailable = false;

        $this->reconcileMarzbanEndpoint();

        if (
            $httpsState === MarzbanHttpsState::Enabled->value
        ) {
            $this->showDrawer = false;
            $this->showSetup = false;
            $this->selectedApplication = null;
        }
    }

    public function render(): View
    {
        return view(
            'livewire.domains.index',
            [
                'server' => $this->server(),
                'endpoints' => $this->endpointPresentation(),
                'availableApplications' => $this->availableApplications(),
                'canAddDomain' => $this->canAddDomain(),
                'selectedEndpoint' => $this->selectedEndpoint(),
            ],
        )->title('دامنه‌ها');
    }

    private function loadManagement(MarzbanManager $manager): void
    {
        $this->unavailable = false;

        try {
            $this->management = $manager
                ->overview(
                    user: $this->authenticatedUser(),
                    server: $this->server(),
                )
                ->toArray();

            $this->reconcileMarzbanEndpoint();
            $this->unavailable = false;
        } catch (Throwable $exception) {
            report($exception);

            $this->management = [];
            $this->unavailable = true;
        } finally {
            $this->loaded = true;
        }
    }

    private function reconcileMarzbanEndpoint(): void
    {
        $httpsState = data_get(
            $this->management,
            'https.state',
        );

        $domain = data_get(
            $this->management,
            'https.domain',
        );

        if (
            $httpsState !== MarzbanHttpsState::Enabled->value
            || ! is_string($domain)
            || trim($domain) === ''
        ) {
            return;
        }

        try {
            $normalizedDomain = PublicEndpointDomain::from(
                $domain,
            )->value;

            $endpoint = PublicEndpoint::query()
                ->firstOrNew([
                    'server_id' => $this->serverId,
                    'application_type' => ApplicationType::Marzban->value,
                ]);

            $endpoint->domain = $normalizedDomain;
            $endpoint->activated_at ??= now();
            $endpoint->save();
        } catch (
            InvalidPublicEndpointDomainException|QueryException $exception
        ) {
            report($exception);

            $this->endpointError =
                'دامنه فعال روی سرور با وضعیت ثبت‌شده xDeploy همگام نشد. پیش از تغییر HTTPS وضعیت دامنه را بررسی کنید.';
        }
    }

    /**
     * @return list<array{
     *     id: int,
     *     application_type: string,
     *     application_name: string,
     *     domain: string,
     *     state: string,
     *     open_url: ?string,
     *     application_url: string,
     *     active: bool
     * }>
     */
    private function endpointPresentation(): array
    {
        return $this->endpoints()
            ->map(function (PublicEndpoint $endpoint): array {
                $active = $endpoint->isActive();

                return [
                    'id' => (int) $endpoint->getKey(),
                    'application_type' => $endpoint->application_type->value,
                    'application_name' => $this->applicationName(
                        $endpoint->application_type,
                    ),
                    'domain' => $endpoint->domain,
                    'state' => $active
                        ? $this->activeEndpointState($endpoint)
                        : 'pending',
                    'open_url' => $active
                        ? $this->applicationOpenUrl($endpoint)
                        : null,
                    'application_url' => route(
                        'panel.servers.applications.show',
                        [
                            'server' => $this->serverId,
                            'application' => $endpoint->application_type->value,
                        ],
                    ),
                    'active' => $active,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     type: string,
     *     name: string,
     *     description: string,
     *     icon: string
     * }>
     */
    private function availableApplications(): array
    {
        if (
            ! $this->loaded
            || $this->unavailable
            || data_get(
                $this->management,
                'application.is_installed',
                false,
            ) !== true
            || ! $this->applicationCanReceiveEndpoint(
                ApplicationType::Marzban,
            )
        ) {
            return [];
        }

        return [[
            'type' => ApplicationType::Marzban->value,
            'name' => 'Marzban',
            'description' => 'پنل مدیریت Marzban را روی دامنه یا زیردامنه خود منتشر کنید.',
            'icon' => 'lucide.box',
        ]];
    }

    private function applicationCanReceiveEndpoint(
        ApplicationType $type,
    ): bool {
        if ($type !== ApplicationType::Marzban) {
            return false;
        }

        if (
            data_get(
                $this->management,
                'application.is_installed',
                false,
            ) !== true
        ) {
            return false;
        }

        return ! PublicEndpoint::query()
            ->where(
                'server_id',
                $this->serverId,
            )
            ->where(
                'application_type',
                $type->value,
            )
            ->exists();
    }

    private function canAddDomain(): bool
    {
        return $this->availableApplications() !== [];
    }

    private function selectedApplicationType(): ?ApplicationType
    {
        if ($this->selectedApplication === null) {
            return null;
        }

        return ApplicationType::tryFrom(
            $this->selectedApplication,
        );
    }

    private function selectedEndpoint(): ?PublicEndpoint
    {
        $type = $this->selectedApplicationType();

        if ($type === null) {
            return null;
        }

        return PublicEndpoint::query()
            ->where(
                'server_id',
                $this->serverId,
            )
            ->where(
                'application_type',
                $type->value,
            )
            ->first();
    }

    /**
     * @return Collection<int, PublicEndpoint>
     */
    private function endpoints(): Collection
    {
        return PublicEndpoint::query()
            ->where(
                'server_id',
                $this->serverId,
            )
            ->orderBy('id')
            ->get();
    }

    private function endpoint(
        int $endpointId,
    ): PublicEndpoint {
        return PublicEndpoint::query()
            ->where(
                'server_id',
                $this->serverId,
            )
            ->findOrFail(
                $endpointId,
            );
    }

    private function activeEndpointState(
        PublicEndpoint $endpoint,
    ): string {
        if ($endpoint->application_type !== ApplicationType::Marzban) {
            return 'unknown';
        }

        $runtimeState = data_get(
            $this->management,
            'https.state',
        );

        $runtimeDomain = data_get(
            $this->management,
            'https.domain',
        );

        return $runtimeState === MarzbanHttpsState::Enabled->value
            && is_string($runtimeDomain)
            && strtolower(trim($runtimeDomain)) === $endpoint->domain
                ? 'enabled'
                : 'misconfigured';
    }

    private function applicationOpenUrl(
        PublicEndpoint $endpoint,
    ): string {
        return match ($endpoint->application_type) {
            ApplicationType::Marzban => 'https://'.$endpoint->domain.'/dashboard/',
        };
    }

    private function applicationName(
        ApplicationType $type,
    ): string {
        return match ($type) {
            ApplicationType::Marzban => 'Marzban',
        };
    }

    private function removeErrorMessage(
        MarzbanHttpsApplyException $exception,
    ): string {
        if ($exception->recoveryAttempted()) {
            if ($exception->recovered()) {
                return 'حذف دامنه کامل نشد؛ تغییرات با موفقیت بازگردانده شدند و دامنه قبلی همچنان فعال است.';
            }

            return 'حذف دامنه شکست خورد و بازیابی کامل نشد. تا بررسی دستی وضعیت Marzban و Caddy دوباره تلاش نکنید.';
        }

        return match ($exception->failure) {
            MarzbanHttpsApplyFailure::ExistingConfiguration => 'پیکربندی HTTPS روی سرور با دامنه ثبت‌شده xDeploy هم‌خوان نیست. وضعیت دامنه را بروزرسانی و بررسی کنید.',
            MarzbanHttpsApplyFailure::OperationInProgress => 'یک عملیات HTTPS دیگر روی این سرور در حال اجرا است. پس از پایان آن دوباره تلاش کنید.',
            MarzbanHttpsApplyFailure::EnvironmentUnavailable => 'ابزارها یا فایل‌های لازم برای حذف امن دامنه در دسترس نیستند. ساختار نصب Marzban را بررسی کنید.',
            MarzbanHttpsApplyFailure::CandidateValidation => 'تنظیمات امن برای حذف دامنه معتبر نبود و دامنه قبلی حفظ شد.',
            MarzbanHttpsApplyFailure::Mutation,
            MarzbanHttpsApplyFailure::Verification => 'حذف دامنه کامل نشد. وضعیت Marzban و Caddy را بروزرسانی و دوباره بررسی کنید.',
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

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
