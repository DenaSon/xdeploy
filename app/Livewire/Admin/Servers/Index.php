<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Servers;

use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

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

    public function render(): View
    {
        $search = trim($this->search);

        $servers = Server::query()
            ->with('user')
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
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%"),
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
