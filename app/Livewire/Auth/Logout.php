<?php

namespace App\Livewire\Auth;

use App\Application\Authentication\Actions\LogoutAction;
use Livewire\Component;

final class Logout extends Component
{
    public function logout(
        LogoutAction $logout,
    ): void {
        $logout->handle();

        $this->redirectRoute('login', navigate: true);
    }
}
