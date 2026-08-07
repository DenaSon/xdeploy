<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Application\Authentication\Actions\VerifyOtpAction;
use App\Application\Authentication\Services\OtpClientRateLimiter;
use App\Domain\Authentication\DTOs\VerifyOtpData;
use App\Domain\Authentication\Exceptions\InvalidOtpException;
use App\Domain\Authentication\Exceptions\OtpExpiredException;
use App\Domain\Authentication\Exceptions\TooManyOtpAttemptsException;
use App\Domain\Authentication\Services\OtpService;
use App\Domain\User\ValueObjects\PhoneNumber;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
final class VerifyOtpPage extends Component
{
    private const string PENDING_PHONE_SESSION_KEY =
        'auth.pending_otp_phone';

    public string $phone = '';

    #[Validate('required|digits:5')]
    public string $code = '';

    public function mount(): void
    {
        $phone = session()->get(
            self::PENDING_PHONE_SESSION_KEY,
        );

        if (! is_string($phone)) {
            $this->redirectToLogin();

            return;
        }

        try {
            $this->phone = (string) PhoneNumber::from(
                $phone,
            );
        } catch (InvalidArgumentException) {
            $this->clearPendingPhone();

            $this->redirectToLogin();
        }
    }

    public function verify(
        VerifyOtpAction $verifyOtp,
        OtpClientRateLimiter $clientRateLimiter,
    ): void {
        $this->validate();

        $clientIdentifier = $this->clientIdentifier();

        try {
            $clientRateLimiter->guardVerification(
                $clientIdentifier,
            );

            $verifyOtp->handle(
                VerifyOtpData::from(
                    phone: $this->phone,
                    code: $this->code,
                ),
            );

            $this->clearPendingPhone();

            $this->redirectRoute(
                name: 'panel.servers.index',
                navigate: true,
            );
        } catch (
            TooManyOtpAttemptsException $exception
        ) {
            $this->showRateLimitError(
                $exception,
            );
        } catch (
            InvalidOtpException $exception
        ) {
            $this->handleInvalidOtp(
                exception: $exception,
                clientIdentifier: $clientIdentifier,
                clientRateLimiter: $clientRateLimiter,
            );
        } catch (
            OtpExpiredException $exception
        ) {
            $this->addError(
                'code',
                $exception->getMessage(),
            );
        }
    }

    public function changePhone(
        OtpService $otpService,
    ): void {
        $otpService->delete(
            PhoneNumber::from(
                $this->phone,
            ),
        );

        $this->clearPendingPhone();

        $this->redirectToLogin();
    }

    public function render(): View
    {
        return view(
            'livewire.auth.verify-otp-page',
        )->title(
            'تأیید شماره موبایل',
        );
    }

    private function handleInvalidOtp(
        InvalidOtpException $exception,
        string $clientIdentifier,
        OtpClientRateLimiter $clientRateLimiter,
    ): void {
        try {
            $clientRateLimiter
                ->recordFailedVerification(
                    $clientIdentifier,
                );
        } catch (
            TooManyOtpAttemptsException $rateLimitException
        ) {
            $this->showRateLimitError(
                $rateLimitException,
            );

            return;
        }

        $this->addError(
            'code',
            $exception->getMessage(),
        );
    }

    private function clientIdentifier(): string
    {
        $ip = request()->ip();

        if (is_string($ip) && $ip !== '') {
            return $ip;
        }

        return 'session:'.session()->getId();
    }

    private function clearPendingPhone(): void
    {
        session()->forget(
            self::PENDING_PHONE_SESSION_KEY,
        );
    }

    private function redirectToLogin(): void
    {
        $this->redirectRoute(
            name: 'login',
            navigate: true,
        );
    }

    private function showRateLimitError(
        TooManyOtpAttemptsException $exception,
    ): void {
        $seconds = max(
            1,
            $exception->retryAfterSeconds,
        );

        $this->addError(
            'code',
            sprintf(
                'تعداد تلاش‌ها بیش از حد مجاز است. %d ثانیه دیگر دوباره تلاش کنید.',
                $seconds,
            ),
        );
    }
}
