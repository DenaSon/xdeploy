<?php

declare(strict_types=1);

namespace App\Livewire\Applications\Marzban;

use App\Application\Applications\Marzban\MarzbanManager;
use App\Domain\Application\Marzban\Exceptions\InvalidMarzbanDomainException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsPreflightException;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsApplyFailure;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

final class SetupHttps extends Component
{
    #[Locked]
    public int $serverId;

    public string $domain = '';

    /**
     * @var array{
     *     domain: string,
     *     server_ipv4_address: string,
     *     resolved_ipv4_addresses: list<string>,
     *     resolved_ipv6_addresses: list<string>,
     *     ipv4_matches_server: bool,
     *     has_incompatible_ipv6: bool,
     *     ready: bool
     * }|null
     */
    public ?array $dnsPreflight = null;

    /**
     * @var array{
     *     layout_state: string,
     *     layout_supported: bool,
     *     managed_caddy_detected: bool,
     *     has_port_conflict: bool,
     *     ready: bool,
     *     ports: array<int, array<string, int|string|bool>>
     * }|null
     */
    public ?array $serverPreflight = null;

    public ?string $preflightError = null;

    public ?string $activationError = null;

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'domain' => [
                'required',
                'string',
                'max:254',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'domain' => 'دامنه',
        ];
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
        MarzbanManager $manager,
    ): void {
        $this->validate();

        $this->dnsPreflight = null;
        $this->serverPreflight = null;
        $this->preflightError = null;
        $this->activationError = null;

        try {
            $server = Server::query()->findOrFail(
                $this->serverId,
            );

            $user = $this->authenticatedUser();

            $server = Server::query()
                ->ownedBy($user)
                ->findOrFail(
                    $this->serverId,
                );

            $result = $manager->preflightHttps(
                user: $user,
                server: $server,
                domain: $this->domain,
            );
            $this->domain = $result->dns->domain;
            $this->dnsPreflight = $result->dns->toArray();
            $this->serverPreflight = $result->server?->toArray();
        } catch (InvalidMarzbanDomainException) {
            $this->addError(
                'domain',
                'فقط دامنه یا ساب‌دامنه معتبر وارد کنید؛ بدون پروتکل، مسیر، پورت یا Wildcard.',
            );
        } catch (MarzbanHttpsPreflightException $exception) {
            report($exception);

            $this->preflightError =
                'بررسی DNS کامل نشد. اتصال سرور و دسترسی عمومی DNS را بررسی کنید.';
        } catch (Throwable $exception) {
            report($exception);

            $this->preflightError =
                'بررسی دامنه با خطا مواجه شد. پس از بررسی اتصال دوباره تلاش کنید.';
        }
    }

    public function activateHttps(
        MarzbanManager $manager,
    ): void {
        $this->validate();

        $this->activationError = null;

        if (! $this->readyForActivation()) {
            $this->activationError =
                'پیش از فعال‌سازی، بررسی آمادگی دامنه و سرور را دوباره اجرا کنید.';

            return;
        }

        try {
            $server = Server::query()->findOrFail(
                $this->serverId,
            );
            $user = $this->authenticatedUser();

            $server = Server::query()
                ->ownedBy($user)
                ->findOrFail(
                    $this->serverId,
                );

            $management = $manager->enableHttps(
                user: $user,
                server: $server,
                domain: $this->domain,
            )->toArray();

            $this->dispatch(
                "marzban-management-updated.{$this->serverId}",
                management: $management,
            );
        } catch (InvalidMarzbanDomainException) {
            $this->addError(
                'domain',
                'فقط دامنه یا ساب‌دامنه معتبر وارد کنید؛ بدون پروتکل، مسیر، پورت یا Wildcard.',
            );
        } catch (MarzbanHttpsPreflightException $exception) {
            report($exception);

            $this->dnsPreflight = null;
            $this->serverPreflight = null;
            $this->activationError =
                'آمادگی دامنه یا سرور تغییر کرده است. بررسی آمادگی را دوباره اجرا کنید.';
        } catch (MarzbanHttpsApplyException $exception) {
            report($exception);

            $this->activationError = $this->applyErrorMessage(
                $exception,
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->activationError =
                'فعال‌سازی HTTPS با خطای پیش‌بینی‌نشده متوقف شد. وضعیت Marzban را بروزرسانی و دوباره بررسی کنید.';
        }
    }

    public function render(): View
    {
        return view(
            'livewire.applications.marzban.setup-https',
        );
    }

    private function readyForActivation(): bool
    {
        return ($this->dnsPreflight['ready'] ?? false) === true
            && ($this->serverPreflight['ready'] ?? false) === true
            && ($this->dnsPreflight['domain'] ?? null) === $this->domain;
    }

    private function applyErrorMessage(
        MarzbanHttpsApplyException $exception,
    ): string {
        if ($exception->recoveryAttempted()) {
            if ($exception->recovered()) {
                return 'فعال‌سازی کامل نشد؛ تغییرات با موفقیت بازگردانده شدند و Marzban در وضعیت قبلی اجرا می‌شود.';
            }

            return 'فعال‌سازی شکست خورد و بازیابی کامل نشد. تا بررسی دستی وضعیت Marzban و Caddy دوباره تلاش نکنید.';
        }

        return match ($exception->failure) {
            MarzbanHttpsApplyFailure::ExistingConfiguration => 'وضعیت HTTPS روی سرور تغییر کرده است. صفحه مدیریت را بروزرسانی کنید.',
            MarzbanHttpsApplyFailure::OperationInProgress => 'یک عملیات HTTPS دیگر روی این سرور در حال اجرا است. پس از پایان آن دوباره تلاش کنید.',
            MarzbanHttpsApplyFailure::EnvironmentUnavailable => 'ابزارها یا فایل‌های لازم برای اعمال امن HTTPS در دسترس نیستند. ساختار نصب Marzban را بررسی کنید.',
            MarzbanHttpsApplyFailure::CandidateValidation => 'تنظیمات پیشنهادی معتبر نبود و هیچ تغییری روی فایل‌های اصلی اعمال نشد.',
            MarzbanHttpsApplyFailure::Mutation,
            MarzbanHttpsApplyFailure::Verification => 'فعال‌سازی HTTPS کامل نشد. پیش از تلاش دوباره وضعیت Marzban را بروزرسانی کنید.',
        };
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
