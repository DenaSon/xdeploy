<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Application\Authentication\Actions\RequestOtpAction;
use App\Domain\Authentication\DTOs\RequestOtpData;
use App\Domain\Authentication\Exceptions\TooManyOtpRequestsException;
use App\Domain\User\ValueObjects\PhoneNumber;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

#[Layout('layouts.auth')]
final class LoginPage extends Component
{
    use Toast;

    #[Validate('required')]
    public string $phone = '';

    public function sendOtp(
        RequestOtpAction $requestOtp,
    ): void {
        $this->resetErrorBag();

        try {
            $requestOtp->handle(
                new RequestOtpData(
                    phone: PhoneNumber::from(
                        $this->phone,
                    ),
                ),
            );

            $this->success(
                title: 'کد تأیید ارسال شد',
                description: 'کد تأیید برای شماره موبایل شما ارسال شد.',
            );

//            $this->redirectRoute(
//                'verify',
//                navigate: true,
//            );
        }
        catch (TooManyOtpRequestsException) {

            $this->warning(
                title: 'کمی صبر کنید',
                description: 'شما بیش از حد مجاز درخواست کد تأیید ارسال کرده‌اید. لطفاً چند دقیقه دیگر دوباره تلاش کنید.',
            );

        }
        catch (\InvalidArgumentException $e) {

            $this->addError(
                'phone',
                'شماره موبایل معتبر نیست.',
            );

        } catch (\Throwable $e) {


            report($e);

            $this->error(
                title: 'خطا',
                description: 'ارسال کد تأیید با مشکل مواجه شد. دوباره تلاش کنید.',
            );
        }
    }

    public function render()
    {
        return view('livewire.auth.login-page');
    }
}
