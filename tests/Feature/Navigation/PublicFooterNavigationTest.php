<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Application\Navigation\PublicFooterNavigation;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class PublicFooterNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_footer_only_renders_selected_currently_published_pages_in_configured_order(): void
    {
        $this->createPage(
            title: 'شرایط استفاده',
            slug: 'terms',
            published: true,
            showInFooter: true,
            sortOrder: 20,
        );

        $this->createPage(
            title: 'حریم خصوصی',
            slug: 'privacy',
            published: true,
            showInFooter: true,
            sortOrder: 10,
        );

        $this->createPage(
            title: 'صفحه منتشرشده خارج از فوتر',
            slug: 'published-hidden',
            published: true,
            showInFooter: false,
            sortOrder: 1,
        );

        $this->createPage(
            title: 'صفحه پیش‌نویس',
            slug: 'draft-footer-page',
            published: false,
            showInFooter: true,
            sortOrder: 1,
        );

        Page::query()->create([
            'title' => 'انتشار آینده',
            'slug' => 'future-page',
            'content' => '# انتشار آینده',
            'is_published' => true,
            'published_at' => now()->addHour(),
            'show_in_footer' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('مستندات')
            ->assertSeeInOrder([
                'حریم خصوصی',
                'شرایط استفاده',
            ])
            ->assertSee(route('pages.show', 'privacy'), escape: false)
            ->assertSee(route('pages.show', 'terms'), escape: false)
            ->assertDontSee('صفحه منتشرشده خارج از فوتر')
            ->assertDontSee('صفحه پیش‌نویس')
            ->assertDontSee('انتشار آینده');
    }

    public function test_footer_navigation_is_cached_and_page_changes_invalidate_the_cache(): void
    {
        Cache::forget(PublicFooterNavigation::CACHE_KEY);

        $page = $this->createPage(
            title: 'شرایط استفاده',
            slug: 'terms',
            published: true,
            showInFooter: true,
            sortOrder: 20,
        );

        $navigation = app(PublicFooterNavigation::class);

        self::assertSame(
            ['شرایط استفاده'],
            array_column($navigation->pages(), 'title'),
        );
        self::assertTrue(Cache::has(PublicFooterNavigation::CACHE_KEY));

        $this->createPage(
            title: 'حریم خصوصی',
            slug: 'privacy',
            published: true,
            showInFooter: true,
            sortOrder: 10,
        );

        self::assertFalse(Cache::has(PublicFooterNavigation::CACHE_KEY));

        self::assertSame(
            [
                'حریم خصوصی',
                'شرایط استفاده',
            ],
            array_column($navigation->pages(), 'title'),
        );

        $page->update([
            'show_in_footer' => false,
        ]);

        self::assertFalse(Cache::has(PublicFooterNavigation::CACHE_KEY));
        self::assertSame(
            ['حریم خصوصی'],
            array_column($navigation->pages(), 'title'),
        );
    }

    private function createPage(
        string $title,
        string $slug,
        bool $published,
        bool $showInFooter,
        int $sortOrder,
    ): Page {
        return Page::query()->create([
            'title' => $title,
            'slug' => $slug,
            'content' => '# '.$title,
            'is_published' => $published,
            'published_at' => $published
                ? now()->subMinute()
                : null,
            'show_in_footer' => $showInFooter,
            'sort_order' => $sortOrder,
        ]);
    }
}
