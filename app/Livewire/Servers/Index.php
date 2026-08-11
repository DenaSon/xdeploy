<?php

declare(strict_types=1);

namespace App\Livewire\Servers;

use App\Application\Server\Actions\DeleteServerAction;
use App\Application\Server\Actions\GetServersAction;
use App\Domain\Server\Exceptions\CloudServerDeletionNotAllowedException;
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

    public function mount(
        GetServersAction $action,
    ): void {
        $this->servers = $action->handle(
            $this->authenticatedUser(),
        );
    }

    public function delete(
        int $serverId,
        DeleteServerAction $action,
    ): void {
        try {
            $action->handle(
                user: $this->authenticatedUser(),
                serverId: $serverId,
            );
        } catch (CloudServerDeletionNotAllowedException) {
            $this->warning(
                'امکان حذف وجود ندارد',
                'سرورهای خریداری‌شده از xDeploy از این بخش قابل حذف نیستند.',
            );

            return;
        }

        $this->servers = $this->servers
            ->reject(
                static fn (
                    Server $server,
                ): bool => $server->getKey()
                    === $serverId,
            )
            ->values();

        $this->success(
            'حذف شد',
            'سرور با موفقیت از xDeploy حذف شد.',
        );
    }

    public function render(): View
    {
        return view(
            'livewire.servers.index',
        )->layout(
            'layouts.panel',
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
