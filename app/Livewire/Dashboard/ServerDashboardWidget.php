<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Application\Server\ServerReadExecutor;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Infrastructure\Linux\Exceptions\OperatingSystemInspectionException;
use App\Infrastructure\SSH\Exceptions\SSHCommandUnavailableException;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Infrastructure\SSH\Exceptions\SSHPasswordChangeRequiredException;
use App\Models\Server;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

abstract class ServerDashboardWidget extends Component
{
    private const string CACHE_PREFIX = 'dashboard:v1:server';

    #[Locked]
    public int $serverId;

    public ?string $errorTitle = null;

    public ?string $errorMessage = null;

    protected function initializeServer(
        int $serverId,
    ): void {
        $this->serverId = $serverId;
    }

    /**
     * Run a widget-specific read against the authenticated user's server.
     *
     * When a cache segment and TTL are provided, only successful results are
     * stored. Manual reloads can bypass the cached value by setting $fresh.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $read
     * @return TResult|null
     */
    protected function read(
        ServerReadExecutor $executor,
        Closure $read,
        ?string $cacheSegment = null,
        int $cacheTtlSeconds = 0,
        bool $fresh = false,
    ): mixed {
        $server = $this->resolveOwnedServer();

        try {
            $result = $this->executeRead(
                server: $server,
                executor: $executor,
                read: $read,
                cacheSegment: $cacheSegment,
                cacheTtlSeconds: $cacheTtlSeconds,
                fresh: $fresh,
            );

            $this->clearError();

            return $result;
        } catch (
            SSHPasswordChangeRequiredException
        ) {
            $this->setError(
                title: 'تغییر رمز عبور SSH الزامی است',
                message: 'سرور اتصال SSH را پذیرفت، اما سیستم‌عامل پیش از اجرای دستورات درخواست تغییر رمز عبور دارد.',
            );
        } catch (
            SSHCommandUnavailableException
        ) {
            $this->setError(
                title: 'امکان اجرای دستورات وجود ندارد',
                message: 'اتصال SSH برقرار است، اما xDeploy نمی‌تواند دستورات لازم برای این بخش را روی سرور اجرا کند.',
            );
        } catch (
            UnsupportedOperatingSystemException $exception
        ) {
            $this->setError(
                title: 'سیستم‌عامل پشتیبانی نمی‌شود',
                message: sprintf(
                    'سیستم‌عامل شناسایی‌شده %s است. در حال حاضر xDeploy فقط Ubuntu و Debian را پشتیبانی می‌کند.',
                    $exception->operatingSystem->displayName(),
                ),
            );
        } catch (
            OperatingSystemInspectionException
        ) {
            $this->setError(
                title: 'شناسایی سیستم‌عامل ناموفق بود',
                message: 'xDeploy نتوانست اطلاعات سیستم‌عامل سرور را برای اجرای این بخش بررسی کند.',
            );
        } catch (
            SSHConnectionException
        ) {
            $this->setError(
                title: 'اتصال SSH برقرار نشد',
                message: 'ارتباط با سرور برقرار نشد. وضعیت شبکه، پورت SSH و اطلاعات ورود را بررسی کنید.',
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->setError(
                title: 'دریافت اطلاعات ناموفق بود',
                message: 'دریافت اطلاعات این بخش از سرور با خطای غیرمنتظره‌ای مواجه شد.',
            );
        }

        return null;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $read
     * @return TResult
     */
    private function executeRead(
        Server $server,
        ServerReadExecutor $executor,
        Closure $read,
        ?string $cacheSegment,
        int $cacheTtlSeconds,
        bool $fresh,
    ): mixed {
        if (
            $cacheSegment === null
            || $cacheTtlSeconds <= 0
        ) {
            return $executor->execute(
                $server,
                $read,
            );
        }

        $cacheKey = $this->cacheKey(
            $server,
            $cacheSegment,
        );

        if ($fresh) {
            Cache::forget(
                $cacheKey,
            );
        }

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(
                $cacheTtlSeconds,
            ),
            static fn (): mixed => $executor->execute(
                $server,
                $read,
            ),
        );
    }

    private function cacheKey(
        Server $server,
        string $segment,
    ): string {
        return sprintf(
            '%s:%d:%s',
            self::CACHE_PREFIX,
            (int) $server->getKey(),
            $segment,
        );
    }

    private function resolveOwnedServer(): Server
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user
            ->servers()
            ->whereKey($this->serverId)
            ->firstOrFail();
    }

    private function setError(
        string $title,
        string $message,
    ): void {
        $this->errorTitle = $title;
        $this->errorMessage = $message;
    }

    private function clearError(): void
    {
        $this->errorTitle = null;
        $this->errorMessage = null;
    }
}
