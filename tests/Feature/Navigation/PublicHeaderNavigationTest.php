<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Application\Navigation\PublicDocumentationNavigation;
use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class PublicHeaderNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_header_renders_guidance_mega_menu_with_published_documentation_categories(): void
    {
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

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('راهنما')
            ->assertSee('نحوه عملکرد')
            ->assertSee('قابلیت‌ها')
            ->assertSee('آموزش‌ها')
            ->assertSee('مدیریت سرور')
            ->assertSee('مشاهده همه آموزش‌ها')
            ->assertSee(
                route('docs.index').'#docs-category-server-management',
                escape: false,
            )
            ->assertDontSee('دسته پیش‌نویس')
            ->assertDontSee('دسته بدون مقاله منتشرشده');
    }

    public function test_documentation_navigation_is_cached_and_content_changes_invalidate_the_cache(): void
    {
        Cache::forget(PublicDocumentationNavigation::CACHE_KEY);

        $category = $this->createCategory(
            title: 'شروع کار',
            slug: 'getting-started',
            published: true,
        );

        $this->createArticle(
            category: $category,
            title: 'اولین راهنما',
            slug: 'first-guide',
            published: true,
        );

        $navigation = app(PublicDocumentationNavigation::class);

        self::assertSame(
            ['شروع کار'],
            array_column($navigation->categories(), 'title'),
        );
        self::assertTrue(
            Cache::has(PublicDocumentationNavigation::CACHE_KEY),
        );

        $secondCategory = $this->createCategory(
            title: 'برنامه‌ها',
            slug: 'applications',
            published: true,
        );

        $this->createArticle(
            category: $secondCategory,
            title: 'راهنمای برنامه‌ها',
            slug: 'applications-guide',
            published: true,
        );

        self::assertFalse(
            Cache::has(PublicDocumentationNavigation::CACHE_KEY),
        );

        self::assertSame(
            [
                'شروع کار',
                'برنامه‌ها',
            ],
            array_column($navigation->categories(), 'title'),
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
