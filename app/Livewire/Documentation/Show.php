<?php

declare(strict_types=1);

namespace App\Livewire\Documentation;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use App\Support\Seo\PublicSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('مستندات')]
final class Show extends Component
{
    #[Locked]
    public int $articleId;

    public function mount(string $categorySlug, string $articleSlug): void
    {
        $category = DocumentationCategory::query()
            ->published()
            ->where('slug', $categorySlug)
            ->firstOrFail();

        $article = $category
            ->articles()
            ->published()
            ->where('slug', $articleSlug)
            ->firstOrFail();

        $this->articleId = (int) $article->getKey();
    }

    public function render(): View
    {
        $article = DocumentationArticle::query()
            ->published()
            ->with('category')
            ->findOrFail($this->articleId);

        abort_unless($article->category->is_published, 404);

        $categories = DocumentationCategory::query()
            ->published()
            ->whereHas(
                'articles',
                fn ($query) => $query->published(),
            )
            ->with([
                'articles' => fn ($query) => $query
                    ->published()
                    ->ordered(),
            ])
            ->ordered()
            ->get();

        $orderedArticles = $categories
            ->flatMap(
                fn (DocumentationCategory $category) => $category->articles->map(
                    fn (DocumentationArticle $item): array => [
                        'id' => (int) $item->getKey(),
                        'title' => $item->title,
                        'category' => $category->title,
                        'url' => route('docs.show', [$category->slug, $item->slug]),
                    ],
                ),
            )
            ->values();

        $currentIndex = $orderedArticles->search(
            fn (array $item): bool => $item['id'] === (int) $article->getKey(),
        );

        $previousArticle = null;
        $nextArticle = null;

        if ($currentIndex !== false) {
            if ($currentIndex > 0) {
                $previousArticle = $orderedArticles->get($currentIndex - 1);
            }

            if ($currentIndex < $orderedArticles->count() - 1) {
                $nextArticle = $orderedArticles->get($currentIndex + 1);
            }
        }

        return view(
            'livewire.documentation.show',
            [
                'article' => $article,
                'categories' => $categories,
                'previousArticle' => $previousArticle,
                'nextArticle' => $nextArticle,
                'renderedContent' => Str::markdown(
                    $article->content ?? '',
                    [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ],
                ),
            ],
        )->layout('layouts.public', [
            'seo' => app(PublicSeo::class)->documentationArticle($article),
        ]);
    }
}
