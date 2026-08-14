<?php

declare(strict_types=1);

namespace App\Livewire\PublicEndpoints;

use App\Application\PublicEndpoint\Operations\QueuePublicEndpointOperationAction;
use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
use App\Application\Server\Operations\Exceptions\ServerMutationInProgressException;
use App\Application\Server\Operations\ServerMutationGuard;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationFailure;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationStatus;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationType;
use App\Domain\PublicEndpoint\Exceptions\InvalidPublicEndpointDomainException;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Models\PublicEndpoint;
use App\Models\PublicEndpointOperation;
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

    #[Locked]
    public ?int $operationId = null;

    public ?string $operationStatus = null;

    public bool $operationActive = false;

    public string $domain = '';

    /** @var array<string, mixed>|null */
    public ?array $dnsPreflight = null;

    /** @var array<string, mixed>|null */
    public ?array $serverPreflight = null;

    public ?string $preflightError = null;

    public ?string $activationError = null;

    public ?string $activationSuccess = null;

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

        $this->syncActiveOperation();
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
        if ($this->operationActive) {
            return;
        }

        $this->dnsPreflight = null;
        $this->serverPreflight = null;
        $this->preflightError = null;
        $this->activationError = null;
        $this->activationSuccess = null;
        $this->resetValidation('domain');
    }

    public function runPreflight(
        PublicEndpointDriverRegistry $drivers,
        ServerMutationGuard $serverMutationGuard,
    ): void {
        if ($this->operationActive) {
            return;
        }

        $this->validate();

        $this->dnsPreflight = null;
        $this->serverPreflight = null;
        $this->preflightError = null;
        $this->activationError = null;
        $this->activationSuccess = null;

        try {
            $server = $this->ownedServer($this->serverId);

            $serverMutationGuard->ensureAvailable(
                $server,
            );

            $domain = PublicEndpointDomain::from($this->domain);

            if (! $this->persistPendingEndpoint($domain)) {
                return;
            }

            $result = $drivers
                ->find($this->type())
                ->preflight(
                    user: $this->authenticatedUser(),
                    server: $server,
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
        } catch (ServerMutationInProgressException) {
            $this->preflightError =
                'یک عملیات دیگر روی این سرور در حال انجام است. پس از پایان آن دوباره تلاش کنید.';
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
        QueuePublicEndpointOperationAction $queueOperation,
    ): void {
        if ($this->operationActive) {
            return;
        }

        $this->validate();
        $this->activationError = null;
        $this->activationSuccess = null;

        if (! $this->readyForActivation()) {
            $this->activationError =
                'پیش از فعال‌سازی، بررسی آمادگی دامنه و سرور را دوباره اجرا کنید.';

            return;
        }

        try {
            $domain = PublicEndpointDomain::from($this->domain);
            $endpoint = $this->pendingEndpoint();

            if ($endpoint->domain !== $domain->value) {
                $this->activationError =
                    'دامنه پس از آخرین بررسی تغییر کرده است. بررسی آمادگی را دوباره اجرا کنید.';

                return;
            }

            $operation = $queueOperation->execute(
                user: $this->authenticatedUser(),
                server: $this->ownedServer($this->serverId),
                endpoint: $endpoint,
                operationType: PublicEndpointOperationType::Enable,
            );

            $this->operationId = (int) $operation->getKey();
            $this->operationStatus = $operation->status->value;
            $this->operationActive = true;
        } catch (InvalidPublicEndpointDomainException) {
            $this->addError(
                'domain',
                'فقط دامنه یا ساب‌دامنه معتبر وارد کنید؛ بدون پروتکل، مسیر، پورت یا Wildcard.',
            );
        } catch (ServerMutationInProgressException) {
            $this->syncActiveOperation();
            $this->activationError =
                'یک عملیات دیگر روی این سرور در حال انجام است. پس از پایان آن دوباره تلاش کنید.';
        } catch (Throwable $exception) {
            report($exception);
            $this->activationError =
                'شروع فعال‌سازی HTTPS با خطا مواجه شد. دوباره تلاش کنید.';
        }
    }

    public function pollOperation(): void
    {
        if ($this->operationId === null) {
            $this->syncActiveOperation();

            return;
        }

        $operation = PublicEndpointOperation::query()
            ->whereKey($this->operationId)
            ->where('user_id', $this->authenticatedUser()->getKey())
            ->where('server_id', $this->serverId)
            ->where('application_type', $this->applicationType)
            ->first();

        if (! $operation instanceof PublicEndpointOperation) {
            $this->operationId = null;
            $this->operationStatus = null;
            $this->operationActive = false;
            $this->activationError =
                'وضعیت عملیات فعال‌سازی در دسترس نیست. صفحه را بروزرسانی کنید.';

            return;
        }

        $this->operationStatus = $operation->status->value;
        $this->operationActive = $operation->isActive();

        if ($this->operationActive) {
            return;
        }

        if ($operation->status === PublicEndpointOperationStatus::Succeeded) {
            $this->activationError = null;
            $this->activationSuccess =
                'دامنه و HTTPS با موفقیت فعال شد.';

            $this->dispatch(
                "public-endpoints-updated.{$this->serverId}",
            );

            return;
        }

        $this->activationSuccess = null;
        $this->activationError = $this->operationFailureMessage(
            $operation->failure_code,
        );

        if ($operation->failure_code === PublicEndpointOperationFailure::Preflight->value) {
            $this->dnsPreflight = null;
            $this->serverPreflight = null;
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

            if ($endpoint !== null) {
                $activeOperationExists = PublicEndpointOperation::query()
                    ->where('public_endpoint_id', $endpoint->getKey())
                    ->active()
                    ->exists();

                if ($activeOperationExists) {
                    $this->endpointId = (int) $endpoint->getKey();
                    $this->domain = $endpoint->domain;
                    $this->syncActiveOperation();
                    $this->activationError =
                        'فعال‌سازی این دامنه از قبل در حال انجام است.';

                    return false;
                }
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

    private function syncActiveOperation(): void
    {
        if ($this->endpointId === null) {
            $this->operationId = null;
            $this->operationStatus = null;
            $this->operationActive = false;

            return;
        }

        $operation = PublicEndpointOperation::query()
            ->where('user_id', $this->authenticatedUser()->getKey())
            ->where('server_id', $this->serverId)
            ->where('public_endpoint_id', $this->endpointId)
            ->where('application_type', $this->applicationType)
            ->active()
            ->latest('id')
            ->first();

        $this->operationId = $operation instanceof PublicEndpointOperation
            ? (int) $operation->getKey()
            : null;
        $this->operationStatus = $operation instanceof PublicEndpointOperation
            ? $operation->status->value
            : null;
        $this->operationActive = $operation instanceof PublicEndpointOperation;
    }

    private function preflightErrorMessage(
        PublicEndpointOperationException $exception,
    ): string {
        return match ($exception->failure) {
            PublicEndpointOperationFailure::Preflight => 'بررسی DNS یا وضعیت سرور کامل نشد. اتصال سرور و تنظیمات دامنه را بررسی کنید.',
            default => 'بررسی آمادگی با خطا متوقف شد. وضعیت برنامه و سرور را بروزرسانی کنید.',
        };
    }

    private function operationFailureMessage(?string $failureCode): string
    {
        return match ($failureCode) {
            PublicEndpointOperationFailure::Preflight->value => 'آمادگی دامنه یا سرور تغییر کرده است. بررسی آمادگی را دوباره اجرا کنید.',
            PublicEndpointOperationFailure::ExistingConfiguration->value => 'وضعیت دامنه روی سرور تغییر کرده است. صفحه دامنه‌ها را بروزرسانی کنید.',
            PublicEndpointOperationFailure::OperationInProgress->value => 'یک عملیات دامنه دیگر روی این سرور در حال اجرا است. پس از پایان آن دوباره تلاش کنید.',
            PublicEndpointOperationFailure::EnvironmentUnavailable->value => 'اتصال SSH یا ابزارهای لازم برای اعمال امن HTTPS در دسترس نیستند.',
            PublicEndpointOperationFailure::CandidateValidation->value => 'تنظیمات پیشنهادی معتبر نبود و هیچ تغییری روی فایل‌های اصلی اعمال نشد.',
            PublicEndpointOperationFailure::Mutation->value,
            PublicEndpointOperationFailure::Verification->value => 'فعال‌سازی HTTPS کامل نشد. تغییرات ایمن‌سازی یا بازیابی شدند؛ وضعیت برنامه را بررسی کنید.',
            'dispatch_failed' => 'عملیات فعال‌سازی در صف اجرا قرار نگرفت. دوباره تلاش کنید.',
            default => 'فعال‌سازی HTTPS در پس‌زمینه با خطا متوقف شد. وضعیت برنامه را بروزرسانی کنید.',
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
