<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Application\Navigation\PublicDocumentationNavigation;
use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class PanelGuidanceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_sidebar_renders_cached_published_documentation_categories_under_guidance_submenu(): void
    {
        $user = User::factory()->create();

        $visibleCategory = $this->createCategory(
            title: 'مدیریت سرور',
            slug: 'server-management',
            published: true,
        );

        $this->createArticle(
            category: $visibleCategory,
            title: 'شروع مدیریت سرور',
            slug: 'getting-started',
            published: true,
        );

        $draftCategory = $this->createCategory(
            title: 'دسته پیش‌نویس',
            slug: 'draft-category',
            published: false,
        );

        $this->createArticle(
            category: $draftCategory,
            title: 'مقاله منتشرشده در دسته پیش‌نویس',
            slug: 'draft-parent-article',
            published: true,
        );

        $emptyPublishedCategory = $this->createCategory(
            title: 'دسته بدون مقاله منتشرشده',
            slug: 'empty-category',
            published: true,
        );

        $this->createArticle(
            category: $emptyPublishedCategory,
            title: 'مقاله پیش‌نویس',
            slug: 'draft-article',
            published: false,
        );

        Cache::forget(PublicDocumentationNavigation::CACHE_KEY);

        $this->actingAs($user)
            ->get(route('panel.servers.index'))
            ->assertOk()
            ->assertSee('پشتیبانی')
            ->assertSee('راهنما')
            ->assertSee('همه آموزش‌ها')
            ->assertSee('مدیریت سرور')
            ->assertSee(route('docs.index'), escape: false)
            ->assertSee(
                route('docs.index').'#docs-category-server-management',
                escape: false,
            )
            ->assertDontSee('دسته پیش‌نویس')
            ->assertDontSee('دسته بدون مقاله منتشرشده');

        self::assertTrue(
            Cache::has(PublicDocumentationNavigation::CACHE_KEY),
        );
    }

    private function createCategory(
        string $title,
        string $slug,
        bool $published,
    ): DocumentationCategory {
        return DocumentationCategory::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => 'توضیح '.$title,
            'sort_order' => DocumentationCategory::query()->count() + 1,
            'is_published' => $published,
        ]);
    }

    private function createArticle(
        DocumentationCategory $category,
        string $title,
        string $slug,
        bool $published,
    ): DocumentationArticle {
        return DocumentationArticle::query()->create([
            'category_id' => $category->getKey(),
            'title' => $title,
            'slug' => $slug,
            'excerpt' => 'خلاصه '.$title,
            'content' => '# '.$title,
            'sort_order' => 1,
            'is_published' => $published,
            'published_at' => $published
                ? now()->subMinute()
                : null,
        ]);
    }
}
