<?php

declare(strict_types=1);

namespace App\Livewire\PublicEndpoints;

use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Models\PublicEndpoint;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

final class Setup extends Component
{
    #[Locked]
    public int $serverId;

    #[Locked]
    public string $applicationType;

    #[Locked]
    public ?int $endpointId = null;

    #[Locked]
    public string $applicationName;

    public string $domain = '';

    /** @var array<string, mixed>|null */
    public ?array $dnsPreflight = null;

    /** @var array<string, mixed>|null */
    public ?array $serverPreflight = null;

    public ?string $preflightError = null;

    public ?string $activationError = null;

    public function mount(
        int $serverId,
        string $applicationType,
        string $applicationName,
        ?int $endpointId = null,
    ): void {
        $this->serverId = (int) $this->ownedServer($serverId)->getKey();

        $type = ApplicationType::tryFrom($applicationType);
        abort_unless($type !== null, 404);

        $this->applicationType = $type->value;
        $this->applicationName = trim($applicationName) !== ''
            ? trim($applicationName)
            : $type->value;

        if ($endpointId === null) {
            return;
        }

        $endpoint = PublicEndpoint::query()
            ->where('server_id', $this->serverId)
            ->where('application_type', $type->value)
            ->findOrFail($endpointId);

        $this->endpointId = (int) $endpoint->getKey();
        $this->domain = $endpoint->domain;
    }

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:254'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['domain' => 'دامنه'];
    }

    public function updatedDomain(): void
    {
        $this->dnsPreflight = null;
        $this->serverPreflight = null;
        $this->preflightError = null;
        $this->activationError = null;
        $this->resetValidation('domain');
    }

    public function runPreflight(
        PublicEndpointDriverRegistry $drivers,
    ): void {
        $this->validate();

        $this->dnsPreflight = null;
        $this->serverPreflight = null;
        $this->preflightError = null;
        $this->activationError = null;

        try {
            $domain = PublicEndpointDomain::from($this->domain);

            if (! $this->persistPendingEndpoint($domain)) {
                return;
            }

            $result = $drivers
                ->find($this->type())
                ->preflight(
                    user: $this->authenticatedUser(),
                    server: $this->ownedServer($this->serverId),
                    domain: $domain,
                );

            $this->domain = $result->dns->domain;
            $this->dnsPreflight = $result->dns->toArray();
            $this->serverPreflight = $result->server?->toArray();

            $this->dispatch(
                "public-endpoints-updated.{$this->serverId}",
            );
        } catch (InvalidPublicEndpointDomainException) {
            $this->addError(
                'domain',
                'فقط دامنه یا ساب‌دامنه معتبر وارد کنید؛ بدون پروتکل، مسیر، پورت یا Wildcard.',
            );
        } catch (PublicEndpointOperationException $exception) {
            report($exception);
            $this->preflightError = $this->preflightErrorMessage($exception);
        } catch (Throwable $exception) {
            report($exception);
            $this->preflightError =
                'بررسی دامنه با خطا مواجه شد. پس از بررسی اتصال دوباره تلاش کنید.';
        }
    }

    public function activateEndpoint(
        PublicEndpointDriverRegistry $drivers,
    ): void {
        $this->validate();
        $this->activationError = null;

        if (! $this->readyForActivation()) {
            $this->activationError =
                'پیش از فعال‌سازی، بررسی آمادگی دامنه و سرور را دوباره اجرا کنید.';

            return;
        }

        try {
            $domain = PublicEndpointDomain::from($this->domain);
            $driver = $drivers->find($this->type());
            $status = $driver->enable(
                user: $this->authenticatedUser(),
                server: $this->ownedServer($this->serverId),
                domain: $domain,
            );

            $endpoint = $this->pendingEndpoint();
            $endpoint->domain = $domain->value;
            $endpoint->activated_at = now();
            $endpoint->save();

            $this->dispatch(
                "public-endpoints-updated.{$this->serverId}",
            );

            $this->dispatch(
                "public-endpoint-status-updated.{$this->serverId}",
                application: $this->applicationType,
                status: $status->toArray(),
                openUrl: $driver->openUrl($domain),
            );
        } catch (InvalidPublicEndpointDomainException) {
            $this->addError(
                'domain',
                'فقط دامنه یا ساب‌دامنه معتبر وارد کنید؛ بدون پروتکل، مسیر، پورت یا Wildcard.',
            );
        } catch (PublicEndpointOperationException $exception) {
            report($exception);

            if ($exception->failure === PublicEndpointOperationFailure::Preflight) {
                $this->dnsPreflight = null;
                $this->serverPreflight = null;
            }

            $this->activationError = $this->activationErrorMessage($exception);
        } catch (Throwable $exception) {
            report($exception);
            $this->activationError =
                'فعال‌سازی HTTPS با خطای پیش‌بینی‌نشده متوقف شد. وضعیت برنامه را بروزرسانی و دوباره بررسی کنید.';
        }
    }

    public function render(): View
    {
        return view(
            'livewire.public-endpoints.setup',
        );
    }

    private function persistPendingEndpoint(
        PublicEndpointDomain $domain,
    ): bool {
        try {
            $endpoint = PublicEndpoint::query()
                ->where('server_id', $this->serverId)
                ->where('application_type', $this->applicationType)
                ->first();

            if ($endpoint?->isActive() === true) {
                $this->activationError =
                    'برای این برنامه از قبل یک دامنه فعال ثبت شده است.';

                return false;
            }

            if ($endpoint === null) {
                $endpoint = new PublicEndpoint([
                    'application_type' => $this->type(),
                    'domain' => $domain->value,
                ]);
                $endpoint->server()->associate(
                    $this->ownedServer($this->serverId),
                );
            } else {
                $endpoint->domain = $domain->value;
            }

            $endpoint->activated_at = null;
            $endpoint->save();

            $this->endpointId = (int) $endpoint->getKey();
            $this->domain = $endpoint->domain;

            return true;
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->addError(
                    'domain',
                    'این دامنه قبلاً به برنامه دیگری روی همین سرور متصل شده است.',
                );

                return false;
            }

            throw $exception;
        }
    }

    private function pendingEndpoint(): PublicEndpoint
    {
        abort_if($this->endpointId === null, 409);

        $endpoint = PublicEndpoint::query()
            ->where('server_id', $this->serverId)
            ->where('application_type', $this->applicationType)
            ->findOrFail($this->endpointId);

        abort_if($endpoint->isActive(), 409);

        return $endpoint;
    }

    private function readyForActivation(): bool
    {
        return ($this->dnsPreflight['ready'] ?? false) === true
            && ($this->serverPreflight['ready'] ?? false) === true
            && ($this->dnsPreflight['domain'] ?? null) === $this->domain;
    }

    private function preflightErrorMessage(
        PublicEndpointOperationException $exception,
    ): string {
        return match ($exception->failure) {
            PublicEndpointOperationFailure::Preflight => 'بررسی DNS یا وضعیت سرور کامل نشد. اتصال سرور و تنظیمات دامنه را بررسی کنید.',
            default => 'بررسی آمادگی با خطا متوقف شد. وضعیت برنامه و سرور را بروزرسانی کنید.',
        };
    }

    private function activationErrorMessage(
        PublicEndpointOperationException $exception,
    ): string {
        if ($exception->recoveryAttempted()) {
            if ($exception->recovered()) {
                return 'فعال‌سازی کامل نشد؛ تغییرات با موفقیت بازگردانده شدند و برنامه در وضعیت قبلی اجرا می‌شود.';
            }

            return 'فعال‌سازی شکست خورد و بازیابی کامل نشد. تا بررسی دستی وضعیت برنامه و Caddy دوباره تلاش نکنید.';
        }

        return match ($exception->failure) {
            PublicEndpointOperationFailure::Preflight => 'آمادگی دامنه یا سرور تغییر کرده است. بررسی آمادگی را دوباره اجرا کنید.',
            PublicEndpointOperationFailure::ExistingConfiguration => 'وضعیت دامنه روی سرور تغییر کرده است. صفحه دامنه‌ها را بروزرسانی کنید.',
            PublicEndpointOperationFailure::OperationInProgress => 'یک عملیات دامنه دیگر روی این سرور در حال اجرا است. پس از پایان آن دوباره تلاش کنید.',
            PublicEndpointOperationFailure::EnvironmentUnavailable => 'ابزارها یا فایل‌های لازم برای اعمال امن HTTPS در دسترس نیستند.',
            PublicEndpointOperationFailure::CandidateValidation => 'تنظیمات پیشنهادی معتبر نبود و هیچ تغییری روی فایل‌های اصلی اعمال نشد.',
            PublicEndpointOperationFailure::Mutation,
            PublicEndpointOperationFailure::Verification => 'فعال‌سازی HTTPS کامل نشد. وضعیت برنامه را بروزرسانی و دوباره بررسی کنید.',
        };
    }

    private function type(): ApplicationType
    {
        return ApplicationType::from($this->applicationType);
    }

    private function isUniqueConstraintViolation(
        QueryException $exception,
    ): bool {
        return in_array(
            (string) $exception->getCode(),
            ['19', '23000', '23505'],
            true,
        );
    }

    private function ownedServer(int $serverId): Server
    {
        return Server::query()
            ->ownedBy($this->authenticatedUser())
            ->findOrFail($serverId);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
