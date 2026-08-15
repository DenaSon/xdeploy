<?php

declare(strict_types=1);

namespace App\Livewire\Security;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.panel')]
#[Title('امنیت حساب')]
final class Index extends Component
{
    public ?string $statusMessage = null;

    public function deletePasskey(
        int $passkeyId,
        DeletePasskey $deletePasskey,
    ): void {
        $user = $this->user();

        $passkey = $user->passkeys()
            ->whereKey($passkeyId)
            ->firstOrFail();

        $deletePasskey($user, $passkey);

        $this->statusMessage = 'Passkey با موفقیت حذف شد.';
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
