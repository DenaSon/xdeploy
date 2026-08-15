<?php

declare(strict_types=1);

namespace App\Livewire\Support;

use App\Domain\Support\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.panel')]
#[Title('پشتیبانی')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'status', history: true)]
    public string $filter = 'all';

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
        $user = $this->user();
        $filter = $this->normalizedFilter();

        $requests = SupportRequest::query()
            ->where('user_id', $user->getKey())
            ->with('server')
            ->withCount('messages')
            ->when(
                $filter !== 'all',
                fn (Builder $query) => $query->where(
                    'status',
                    SupportRequestStatus::from($filter),
                ),
            )
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(15);

        $statusCounts = SupportRequest::query()
            ->where('user_id', $user->getKey())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view(
            'livewire.support.index',
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
            : 'all';
    }

    private function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
