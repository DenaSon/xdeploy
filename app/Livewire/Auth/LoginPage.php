<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Application\Authentication\Actions\RequestOtpAction;
use App\Application\Authentication\Services\OtpClientRateLimiter;
use App\Domain\Authentication\DTOs\RequestOtpData;
use App\Domain\Authentication\Exceptions\TooManyOtpRequestsException;
use App\Domain\User\ValueObjects\PhoneNumber;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;
use Throwable;

#[Layout('layouts.guest')]
final class LoginPage extends Component
{
    use Toast;

    private const string PENDING_PHONE_SESSION_KEY =
        'auth.pending_otp_phone';

    #[Validate('required')]
    public string $phone = '';

    public function sendOtp(
        RequestOtpAction $requestOtp,
        OtpClientRateLimiter $clientRateLimiter,
    ): void {
        $this->resetErrorBag();

        try {
            $phone = PhoneNumber::from(
                $this->phone,
            );

            $clientRateLimiter->hitRequest(
                $this->clientIdentifier(),
            );

            $requestOtp->handle(
                new RequestOtpData(
                    phone: $phone,
                ),
            );

            session()->put(
                self::PENDING_PHONE_SESSION_KEY,
                (string) $phone,
            );

            $this->redirectRoute(
                name: 'verify',
                navigate: true,
            );
        } catch (TooManyOtpRequestsException) {
            $this->warning(
                title: 'کمی صبر کنید',
                description: 'تعداد درخواست‌های کد تأیید بیش از حد مجاز است. لطفاً کمی بعد دوباره تلاش کنید.',
            );
        } catch (InvalidArgumentException) {
            $this->addError(
                'phone',
                'شماره موبایل معتبر نیست.',
            );
        } catch (Throwable $exception) {
            report(
                $exception,
            );

            $this->error(
                title: 'خطا',
                description: 'ارسال کد تأیید با مشکل مواجه شد. دوباره تلاش کنید.',
            );
        }
    }

    public function render(): View
    {
        return view(
            'livewire.auth.login-page',
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
}
