<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Application\Authentication\Actions\VerifyOtpAction;
use App\Domain\Authentication\DTOs\VerifyOtpData;
use App\Domain\Authentication\Exceptions\InvalidOtpException;
use App\Domain\Authentication\Exceptions\OtpExpiredException;
use App\Domain\User\ValueObjects\PhoneNumber;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
final class VerifyOtpPage extends Component
{
    public string $phone = '';

    #[Validate('required|digits:4')]
    public string $code = '';

    public function mount(
        string $phone,
    ): void {
        $this->phone = (string) PhoneNumber::from($phone);
    }

    public function verify(
        VerifyOtpAction $verifyOtp,
    ): void {
        $this->validate();

        try {

            $verifyOtp->handle(
                VerifyOtpData::from(
                    phone: $this->phone,
                    code: $this->code,
                ),
            );

            $this->redirectRoute(
                name: 'panel.dashboard',
                navigate: true,
            );

        } catch (InvalidOtpException|OtpExpiredException $e) {

            $this->addError(
                'code',
                $e->getMessage(),
            );

        }
    }

    public function resend(): void
    {
        //
    }

    public function render()
    {
        return view('livewire.auth.verify-otp-page')
            ->title('تأیید شماره موبایل');
    }
}
