<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Security;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('تأیید امنیتی')]
final class ConfirmPasskey extends Component
{
    public function mount(): void
    {
        $user = auth()->user();

        abort_unless(
            $user instanceof User
            && $user->isAdmin(),
            403,
        );

        if (! $user->passkeys()->exists()) {
            $this->redirectRoute(
                name: 'panel.security',
                navigate: true,
            );
        }
    }

    public function render(): View
    {
        return view(
            'livewire.admin.security.confirm-passkey',
        );
    }
}
