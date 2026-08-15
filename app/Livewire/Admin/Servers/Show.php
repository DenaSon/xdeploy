<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Servers;

use App\Application\Authentication\Actions\RequestOtpAction;
use App\Application\Server\Actions\RecordSupportAccessAction;
use App\Application\Server\Actions\TestSupportConnectionAction;
use App\Domain\Authentication\DTOs\RequestOtpData;
use App\Domain\Authentication\DTOs\VerifyOtpData;
use App\Domain\Authentication\Exceptions\InvalidOtpException;
use App\Domain\Authentication\Exceptions\OtpExpiredException;
use App\Domain\Authentication\Exceptions\TooManyOtpAttemptsException;
use App\Domain\Authentication\Exceptions\TooManyOtpRequestsException;
use App\Domain\Authentication\Services\OtpService;
use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\Order;
use App\Models\Server;
use App\Models\SupportAccessLog;
use App\Models\User;
use App\Support\Admin\AdminSupportAccessSession;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.admin')]
#[Title('جزئیات سرور')]
final class Show extends Component
{
    public Server $server;

    public string $supportReason = '';

    public string $supportOtp = '';

    public bool $supportOtpRequested = false;

    public bool $supportAccessConfirmed = false;

    public ?bool $connectionTestPassed = null;

    public ?string $connectionTestMessage = null;

    public function mount(Server $adminServer): void
    {
        $this->server = $adminServer;

        $this->supportAccessConfirmed = app(
            AdminSupportAccessSession::class,
        )->isGranted(
            admin: $this->adminUser(),
            server: $this->server,
        );
    }

    public function requestSupportOtp(
        RequestOtpAction $requestOtp,
    ): void {
        $this->validateSupportReason();

        $admin = $this->adminUser();

        try {
            $requestOtp->handle(
                RequestOtpData::from(
                    (string) $admin->phone,
                ),
            );

            $this->supportOtpRequested = true;
            $this->supportOtp = '';
            $this->resetErrorBag('supportOtp');
        } catch (TooManyOtpRequestsException) {
            $this->addError(
                'supportOtp',
                'تعداد درخواست‌های کد بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->addError(
                'supportOtp',
                'ارسال کد تأیید انجام نشد. دوباره تلاش کنید.',
            );
        }
    }

    public function confirmSupportOtp(
        OtpService $otpService,
        AdminSupportAccessSession $supportAccessSession,
    ): void {
        $this->validateSupportReason();

        $this->validate([
            'supportOtp' => [
                'required',
                'digits:5',
            ],
        ]);

        $admin = $this->adminUser();

        try {
            $data = VerifyOtpData::from(
                phone: (string) $admin->phone,
                code: $this->supportOtp,
            );

            $otpService->validate(
                phone: $data->phone,
                code: $data->code,
            );

            $supportAccessSession->grant(
                admin: $admin,
                server: $this->server,
            );

            $this->supportAccessConfirmed = true;
            $this->supportOtpRequested = false;
            $this->supportOtp = '';
            $this->resetErrorBag('supportOtp');
        } catch (TooManyOtpAttemptsException $exception) {
            $seconds = max(
                1,
                $exception->retryAfterSeconds,
            );

            $this->addError(
                'supportOtp',
                sprintf(
                    'تعداد تلاش‌ها بیش از حد مجاز است. %d ثانیه دیگر دوباره تلاش کنید.',
                    $seconds,
                ),
            );
        } catch (InvalidOtpException) {
            $this->addError(
                'supportOtp',
                'کد تأیید صحیح نیست.',
            );
        } catch (OtpExpiredException) {
            $this->addError(
                'supportOtp',
                'کد تأیید منقضی شده است. یک کد جدید درخواست کنید.',
            );
        } catch (InvalidArgumentException) {
            $this->addError(
                'supportOtp',
                'کد تأیید معتبر نیست.',
            );
        }
    }

    public function testSupportConnection(
        TestSupportConnectionAction $testConnection,
        RecordSupportAccessAction $recordSupportAccess,
    ): void {
        $reason = $this->validateSupportReason();
        $admin = $this->adminUser();
        $successful = false;

        try {
            $testConnection->handle(
                $this->server,
            );

            $successful = true;
            $this->connectionTestPassed = true;
            $this->connectionTestMessage = 'اتصال SSH با موفقیت برقرار شد.';
        } catch (Throwable $exception) {
            report($exception);

            $this->connectionTestPassed = false;
            $this->connectionTestMessage = 'اتصال SSH برقرار نشد. لاگ‌های سیستم را برای جزئیات بیشتر بررسی کنید.';
        } finally {
            $recordSupportAccess->handle(
                admin: $admin,
                server: $this->server,
                action: SupportAccessAction::SshConnectionTest,
                reason: $reason,
                successful: $successful,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            );
        }
    }

    public function render(): View
    {
        $server = $this->server->load('user');

        return view(
            'livewire.admin.servers.show',
            [
                'server' => $server,
                'orders' => Order::query()
                    ->where('server_id', $server->getKey())
                    ->latest('id')
                    ->limit(10)
                    ->get(),
                'supportAccessLogs' => SupportAccessLog::query()
                    ->with('adminUser')
                    ->where('server_id', $server->getKey())
                    ->latest('id')
                    ->limit(10)
                    ->get(),
            ],
        );
    }

    private function validateSupportReason(): string
    {
        $validated = $this->validate([
            'supportReason' => [
                'required',
                'string',
                'min:5',
                'max:500',
            ],
        ]);

        return trim($validated['supportReason']);
    }

    private function adminUser(): User
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User
            && $user->isAdmin(),
            403,
        );

        return $user;
    }
}
