<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Servers;

use App\Application\Cloud\Servers\ExpireCloudServerNowAction;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use LogicException;

#[Layout('layouts.admin')]
#[Title('سرورها')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = 'all';

    #[Url(history: true)]
    public string $source = 'all';

    public ?string $expirationMessage = null;

    public ?string $expirationError = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function expireNow(
        int $serverId,
        ExpireCloudServerNowAction $expireCloudServer,
    ): void {
        $this->expirationMessage = null;
        $this->expirationError = null;

        $admin = auth()->user();

        abort_unless(
            $admin instanceof User
            && $admin->isAdmin(),
            403,
        );

        try {
            $changed = $expireCloudServer->execute(
                admin: $admin,
                serverId: $serverId,
            );
        } catch (LogicException) {
            $this->expirationError = 'این سرور برای انقضای دستی قابل استفاده نیست.';

            return;
        }

        $this->expirationMessage = $changed
            ? 'سرور منقضی شد. Scheduler در اجرای بعدی فرایند حذف را انجام می‌دهد.'
            : 'این سرور قبلاً منقضی شده است.';
    }

    public function render(): View
    {
        $search = trim($this->search);

        $servers = Server::query()
            ->with('user.profile')
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('host', 'like', "%{$search}%")
                            ->orWhereHas(
                                'user',
                                fn (Builder $userQuery) => $userQuery
                                    ->matchesIdentity($search),
                            );
                    },
                ),
            )
            ->when(
                in_array($this->status, [
                    ServerStatus::Active->value,
                    ServerStatus::Inactive->value,
                ], true),
                fn (Builder $query) => $query->where('status', $this->status),
            )
            ->when(
                $this->source === 'cloud',
                fn (Builder $query) => $query
                    ->whereNotNull('cloud_provider')
                    ->whereNotNull('cloud_server_id'),
            )
            ->when(
                $this->source === 'manual',
                fn (Builder $query) => $query
                    ->whereNull('cloud_provider')
                    ->whereNull('cloud_server_id'),
            )
            ->latest('id')
            ->paginate(20);

        return view(
            'livewire.admin.servers.index',
            ['servers' => $servers],
        );
    }
}
