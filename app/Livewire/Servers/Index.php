<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\DeleteServerAction;
use App\Application\Server\Actions\GetServersAction;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Mary\Traits\Toast;

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
        Server $server,
        DeleteServerAction $action,
    ): void {

        $action->handle($server);

        $this->servers = $this->servers->reject(
            fn (Server $item) => $item->is($server)
        );

        $this->success(
            'حذف شد',
            'سرور با موفقیت حذف شد.'
        );
    }

    public function render(): View
    {
        return view('livewire.servers.index');
    }
}
