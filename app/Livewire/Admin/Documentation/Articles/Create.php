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
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('ایجاد مقاله مستندات')]
final class Create extends Component
{
    public string $categoryId = '';

    public string $title = '';

    public string $slug = '';

    public string $excerpt = '';

    public string $content = '';

    public int $sortOrder = 0;

    public bool $isPublished = false;

    public function save()
    {
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
                    ->where(fn (QueryBuilder $query) => $query->where('category_id', (int) $this->categoryId)),
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

        $article = DocumentationArticle::query()->create([
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
                ? now()
                : null,
        ]);

        return redirect()
            ->route('admin.documentation.articles.edit', $article)
            ->with('admin.documentation.saved', 'مقاله مستندات ایجاد شد.');
    }

    public function render(): View
    {
        return view(
            'livewire.admin.documentation.articles.create',
            [
                'categories' => DocumentationCategory::query()
                    ->ordered()
                    ->get(['id', 'title', 'is_published']),
            ],
        );
    }
}
