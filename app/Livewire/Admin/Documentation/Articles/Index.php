<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Documentation\Articles;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('مستندات')]
final class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = 'all';

    #[Url(history: true)]
    public string $category = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);
        $categoryId = ctype_digit($this->category)
            ? (int) $this->category
            : null;

        $articles = DocumentationArticle::query()
            ->with('category')
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%")
                            ->orWhereHas(
                                'category',
                                fn (Builder $query) => $query->where('title', 'like', "%{$search}%"),
                            );
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
            ->when(
                $categoryId !== null,
                fn (Builder $query) => $query->where('category_id', $categoryId),
            )
            ->latest('id')
            ->paginate(20);

        $categories = DocumentationCategory::query()
            ->ordered()
            ->get(['id', 'title']);

        return view(
            'livewire.admin.documentation.articles.index',
            [
                'articles' => $articles,
                'categories' => $categories,
            ],
        );
    }
}
