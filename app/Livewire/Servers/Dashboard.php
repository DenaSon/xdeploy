<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\GetDashboardSnapshotAction;
use App\Application\Server\Data\DashboardSnapshotData;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Infrastructure\Linux\Exceptions\OperatingSystemInspectionException;
use App\Infrastructure\SSH\Exceptions\SSHCommandUnavailableException;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Infrastructure\SSH\Exceptions\SSHConnectionUnavailableException;
use App\Infrastructure\SSH\Exceptions\SSHPasswordChangeRequiredException;
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
    public Server $server;

    /**
     * @var array<string, mixed>
     */
    public array $identity = [];

    /**
     * @var array<string, mixed>
     */
    public array $cpu = [];

    /**
     * @var array<string, mixed>
     */
    public array $resources = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $services = [];

    /**
     * @var array<string, mixed>
     */
    public array $docker = [];

    /**
     * @var list<string>
     */
    public array $loadedSegments = [];

    /**
     * @var array<string, string>
     */
    public array $sectionErrors = [];

    public bool $initialLoadComplete = false;

    public ?string $connectionErrorTitle = null;

    public ?string $connectionErrorMessage = null;

    public ?string $lastUpdatedAt = null;

    public function mount(
        Server $server,
        GetDashboardSnapshotAction $snapshot,
    ): void {
        $this->server = $this
            ->authenticatedUser()
            ->servers()
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        /*
         * Hydrate any still-valid snapshot data during the initial
         * HTML request. This never opens an SSH connection.
         */
        $this->applySnapshot(
            $snapshot->cached(
                $this->server,
            ),
        );
    }

    public function loadDashboard(
        GetDashboardSnapshotAction $snapshot,
    ): void {
        $this->refreshSnapshot(
            action: $snapshot,
        );

        $this->initialLoadComplete = true;
    }

    public function refreshRuntime(
        GetDashboardSnapshotAction $snapshot,
    ): void {
        $this->refreshSnapshot(
            action: $snapshot,
        );
    }

    public function reloadDashboard(
        GetDashboardSnapshotAction $snapshot,
    ): void {
        $this->refreshSnapshot(
            action: $snapshot,
            fresh: true,
        );

        $this->initialLoadComplete = true;
    }

    public function render(): View
    {
        return view(
            'livewire.servers.dashboard',
            [
                'hasSnapshot' => $this->loadedSegments !== [],
            ],
        );
    }

    private function refreshSnapshot(
        GetDashboardSnapshotAction $action,
        bool $fresh = false,
    ): void {
        try {
            $snapshot = $action->handle(
                server: $this->server,
                fresh: $fresh,
            );

            $this->applySnapshot(
                $snapshot,
            );

            $this->clearConnectionError();

            $this->lastUpdatedAt =
                now()->format('H:i:s');
        } catch (
            SSHConnectionUnavailableException $exception
        ) {
            $this->setConnectionError(
                title: 'اتصال موقتاً متوقف شده است',
                message: sprintf(
                    'پس از چند تلاش ناموفق، اتصال SSH برای مدت کوتاهی متوقف شده است. حدود %d ثانیه دیگر دوباره تلاش کنید.',
                    $exception->retryAfterSeconds(),
                ),
            );
        } catch (
            SSHPasswordChangeRequiredException
        ) {
            $this->setConnectionError(
                title: 'تغییر رمز عبور SSH الزامی است',
                message: 'سرور اتصال SSH را پذیرفت، اما سیستم‌عامل پیش از اجرای دستورات درخواست تغییر رمز عبور دارد.',
            );
        } catch (
            SSHCommandUnavailableException
        ) {
            $this->setConnectionError(
                title: 'امکان اجرای دستورات وجود ندارد',
                message: 'اتصال SSH برقرار است، اما xDeploy نمی‌تواند دستورات موردنیاز داشبورد را روی سرور اجرا کند.',
            );
        } catch (
            UnsupportedOperatingSystemException $exception
        ) {
            $this->setConnectionError(
                title: 'سیستم‌عامل پشتیبانی نمی‌شود',
                message: sprintf(
                    'سیستم‌عامل شناسایی‌شده %s است. در حال حاضر xDeploy فقط Ubuntu و Debian را پشتیبانی می‌کند.',
                    $exception
                        ->operatingSystem
                        ->displayName(),
                ),
            );
        } catch (
            OperatingSystemInspectionException
        ) {
            $this->setConnectionError(
                title: 'شناسایی سیستم‌عامل ناموفق بود',
                message: 'xDeploy نتوانست اطلاعات سیستم‌عامل سرور را برای اجرای داشبورد بررسی کند.',
            );
        } catch (
            SSHConnectionException
        ) {
            $this->setConnectionError(
                title: 'اتصال SSH برقرار نشد',
                message: 'ارتباط با سرور برقرار نشد. وضعیت شبکه، پورت SSH و اطلاعات ورود را بررسی کنید.',
            );
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->setConnectionError(
                title: 'دریافت اطلاعات ناموفق بود',
                message: 'دریافت اطلاعات داشبورد با خطای غیرمنتظره‌ای مواجه شد.',
            );
        }
    }

    private function applySnapshot(
        DashboardSnapshotData $snapshot,
    ): void {
        if (
            in_array(
                'identity',
                $snapshot->loadedSegments,
                true,
            )
        ) {
            $this->identity =
                $snapshot->identity;
        }

        if (
            in_array(
                'cpu',
                $snapshot->loadedSegments,
                true,
            )
        ) {
            $this->cpu =
                $snapshot->cpu;
        }

        if (
            in_array(
                'resources',
                $snapshot->loadedSegments,
                true,
            )
        ) {
            $this->resources =
                $snapshot->resources;
        }

        if (
            in_array(
                'services',
                $snapshot->loadedSegments,
                true,
            )
        ) {
            $this->services =
                $snapshot->services;
        }

        if (
            in_array(
                'docker',
                $snapshot->loadedSegments,
                true,
            )
        ) {
            $this->docker =
                $snapshot->docker;
        }

        $this->loadedSegments =
            array_values(
                array_unique([
                    ...$this->loadedSegments,
                    ...$snapshot->loadedSegments,
                ]),
            );

        $this->sectionErrors =
            $snapshot->errors;
    }

    private function setConnectionError(
        string $title,
        string $message,
    ): void {
        $this->connectionErrorTitle = $title;
        $this->connectionErrorMessage = $message;
    }

    private function clearConnectionError(): void
    {
        $this->connectionErrorTitle = null;
        $this->connectionErrorMessage = null;
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
