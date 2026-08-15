<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Documentation;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_docs_index_only_lists_public_categories_with_public_articles(): void
    {
        $publicCategory = $this->createCategory('شروع کار', 'getting-started', true, 10);
        $draftCategory = $this->createCategory('پیش‌نویس', 'draft-category', false, 20);

        $this->createArticle($publicCategory, 'مقاله عمومی', 'public-article', true, now(), 10);
        $this->createArticle($publicCategory, 'مقاله پیش‌نویس', 'draft-article', false, null, 20);
        $this->createArticle($draftCategory, 'مقاله دسته مخفی', 'hidden-category-article', true, now(), 10);

        $this->get(route('docs.index'))
            ->assertOk()
            ->assertSee('شروع کار')
            ->assertSee('مقاله عمومی')
            ->assertDontSee('مقاله پیش‌نویس')
            ->assertDontSee('مقاله دسته مخفی');
    }

    public function test_published_article_is_visible_and_raw_html_is_stripped(): void
    {
        $category = $this->createCategory('سرورها', 'servers', true, 10);
        $article = $this->createArticle(
            $category,
            'اتصال سرور',
            'connect-server',
            true,
            now(),
            10,
            "## اتصال\n\nمتن امن\n\n<mark data-raw-doc=\"1\">raw-marker</mark>",
        );

        $this->get(route('docs.show', [$category->slug, $article->slug]))
            ->assertOk()
            ->assertSee('اتصال سرور')
            ->assertSee('اتصال')
            ->assertSee('raw-marker')
            ->assertDontSee('data-raw-doc', false);
    }

    public function test_article_under_draft_category_is_not_publicly_visible(): void
    {
        $category = $this->createCategory('مخفی', 'hidden', false, 10);
        $article = $this->createArticle($category, 'مقاله', 'article', true, now(), 10);

        $this->get(route('docs.show', [$category->slug, $article->slug]))
            ->assertNotFound();
    }

    public function test_future_published_article_is_not_publicly_visible(): void
    {
        $category = $this->createCategory('شبکه', 'network', true, 10);
        $article = $this->createArticle($category, 'آینده', 'future', true, now()->addHour(), 10);

        $this->get(route('docs.show', [$category->slug, $article->slug]))
            ->assertNotFound();
    }

    private function createCategory(
        string $title,
        string $slug,
        bool $published,
        int $sortOrder,
    ): DocumentationCategory {
        return DocumentationCategory::query()->create([
            'title' => $title,
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'is_published' => $published,
        ]);
    }

    private function createArticle(
        DocumentationCategory $category,
        string $title,
        string $slug,
        bool $published,
        mixed $publishedAt,
        int $sortOrder,
        string $content = 'متن راهنما',
    ): DocumentationArticle {
        return DocumentationArticle::query()->create([
            'category_id' => $category->id,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'sort_order' => $sortOrder,
            'is_published' => $published,
            'published_at' => $publishedAt,
        ]);
    }
}
