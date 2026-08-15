<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Application\Authentication\Actions\DeleteUserPasskeyAction;
use App\Domain\Authentication\Exceptions\CannotDeleteLastAdminPasskeyException;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('امنیت حساب')]
final class Index extends Component
{
    public ?string $statusMessage = null;

    public ?string $securityError = null;

    public function deletePasskey(
        int $passkeyId,
        DeleteUserPasskeyAction $deletePasskey,
    ): void {
        $this->securityError = null;
        $this->statusMessage = null;

        try {
            $deletePasskey->handle(
                user: $this->user(),
                passkeyId: $passkeyId,
            );

            $this->statusMessage = 'Passkey با موفقیت حذف شد.';
        } catch (CannotDeleteLastAdminPasskeyException) {
            $this->securityError = 'آخرین Passkey حساب مدیر قابل حذف نیست. ابتدا یک Passkey دیگر اضافه کنید.';
        }
    }

    public function render(): View
    {
        $user = $this->user();

        return view(
            'livewire.security.index',
            [
                'user' => $user,
                'passkeys' => $user->passkeys()
                    ->latest('id')
                    ->get(),
            ],
        );
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
