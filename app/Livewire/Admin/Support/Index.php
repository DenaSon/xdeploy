<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Support;

use App\Domain\Support\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('پشتیبانی')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'status', history: true)]
    public string $filter = SupportRequestStatus::Open->value;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        abort_unless(
            in_array($filter, $this->allowedFilters(), true),
            422,
        );

        $this->filter = $filter;
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $filter = $this->normalizedFilter();

        $requests = SupportRequest::query()
            ->with([
                'user.profile',
                'server',
            ])
            ->withCount('messages')
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(
                        function (Builder $searchQuery) use ($search): void {
                            $searchQuery
                                ->where('subject', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'user',
                                    fn (Builder $userQuery) => $userQuery
                                        ->matchesIdentity($search),
                                )
                                ->orWhereHas(
                                    'server',
                                    function (Builder $serverQuery) use ($search): void {
                                        $serverQuery
                                            ->where('name', 'like', "%{$search}%")
                                            ->orWhere('host', 'like', "%{$search}%");
                                    },
                                );

                            if (ctype_digit($search)) {
                                $searchQuery->orWhereKey((int) $search);
                            }
                        },
                    );
                },
            )
            ->when(
                $filter !== 'all',
                fn (Builder $query) => $query->where(
                    'status',
                    SupportRequestStatus::from($filter),
                ),
            )
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(25);

        $statusCounts = SupportRequest::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view(
            'livewire.admin.support.index',
            [
                'requests' => $requests,
                'statusCounts' => $statusCounts,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function allowedFilters(): array
    {
        return [
            'all',
            SupportRequestStatus::Open->value,
            SupportRequestStatus::Answered->value,
            SupportRequestStatus::Closed->value,
        ];
    }

    private function normalizedFilter(): string
    {
        return in_array($this->filter, $this->allowedFilters(), true)
            ? $this->filter
            : SupportRequestStatus::Open->value;
    }
}
