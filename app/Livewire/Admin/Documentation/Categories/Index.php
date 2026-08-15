<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Documentation\Categories;

use App\Models\DocumentationCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('دسته‌بندی مستندات')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);

        $categories = DocumentationCategory::query()
            ->withCount('articles')
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    },
                ),
            )
            ->when(
                $this->status === 'published',
                fn (Builder $query) => $query->where('is_published', true),
            )
            ->when(
                $this->status === 'draft',
                fn (Builder $query) => $query->where('is_published', false),
            )
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(20);

        return view(
            'livewire.admin.documentation.categories.index',
            ['categories' => $categories],
        );
    }
}
