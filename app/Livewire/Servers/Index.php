<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\DeleteServerAction;
use App\Application\Server\Actions\GetServersAction;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('سرورها')]
final class Index extends Component
{
    use Toast;

    public Collection $servers;

    public function mount(GetServersAction $action): void
    {
        $this->servers = $action->handle(
            Auth::user()
        );
    }

    public function delete(
        int $serverId,
        DeleteServerAction $action,
    ): void {
        /** @var User $user */
        $user = Auth::user();

        $action->handle(
            user: $user,
            serverId: $serverId,
        );

        $this->servers = $this->servers->reject(
            static fn (Server $server): bool => $server->getKey() === $serverId,
        );

        $this->success(
            'حذف شد',
            'سرور با موفقیت حذف شد.',
        );
    }

    public function render(): View
    {
        return view('livewire.servers.index')->layout('layouts.panel');
    }
}
