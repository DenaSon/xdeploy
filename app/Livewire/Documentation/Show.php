<?php

declare(strict_types=1);

namespace App\Livewire\Documentation;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
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

        return view(
            'livewire.documentation.show',
            [
                'article' => $article,
                'renderedContent' => Str::markdown(
                    $article->content ?? '',
                    [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ],
                ),
            ],
        );
    }
}
