<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Documentation\Articles;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('ویرایش مقاله مستندات')]
final class Edit extends Component
{
    #[Locked]
    public int $articleId;

    public string $categoryId = '';

    public string $title = '';

    public string $slug = '';

    public string $excerpt = '';

    public string $content = '';

    public int $sortOrder = 0;

    public bool $isPublished = false;

    public function mount(DocumentationArticle $article): void
    {
        $this->articleId = (int) $article->getKey();
        $this->categoryId = (string) $article->category_id;
        $this->title = $article->title;
        $this->slug = $article->slug;
        $this->excerpt = $article->excerpt ?? '';
        $this->content = $article->content ?? '';
        $this->sortOrder = $article->sort_order;
        $this->isPublished = $article->is_published;
    }

    public function save()
    {
        $article = DocumentationArticle::query()->findOrFail($this->articleId);
        $this->slug = Str::lower(trim($this->slug));

        $validated = $this->validate([
            'categoryId' => ['required', 'integer', 'exists:documentation_categories,id'],
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::unique('documentation_articles', 'slug')
                    ->where(fn (QueryBuilder $query) => $query->where('category_id', (int) $this->categoryId))
                    ->ignore($article->getKey()),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => [
                Rule::requiredIf($this->isPublished),
                'nullable',
                'string',
                'max:100000',
            ],
            'sortOrder' => ['required', 'integer', 'min:0', 'max:100000'],
            'isPublished' => ['boolean'],
        ]);

        $wasPublished = $article->is_published;

        $article->update([
            'category_id' => (int) $validated['categoryId'],
            'title' => trim($validated['title']),
            'slug' => $validated['slug'],
            'excerpt' => $validated['excerpt'] !== ''
                ? trim($validated['excerpt'])
                : null,
            'content' => $validated['content'] !== ''
                ? $validated['content']
                : null,
            'sort_order' => $validated['sortOrder'],
            'is_published' => $validated['isPublished'],
            'published_at' => $validated['isPublished']
                ? ($wasPublished && $article->published_at !== null
                    ? $article->published_at
                    : now())
                : null,
        ]);

        return redirect()
            ->route('admin.documentation.articles.edit', $article)
            ->with('admin.documentation.saved', 'مقاله مستندات ذخیره شد.');
    }

    public function render(): View
    {
        return view(
            'livewire.admin.documentation.articles.edit',
            [
                'article' => DocumentationArticle::query()
                    ->with('category')
                    ->findOrFail($this->articleId),
                'categories' => DocumentationCategory::query()
                    ->ordered()
                    ->get(['id', 'title', 'is_published']),
            ],
        );
    }
}
