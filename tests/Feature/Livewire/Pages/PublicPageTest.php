<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Pages;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_page_is_not_publicly_visible(): void
    {
        $page = Page::query()->create([
            'title' => 'پیش‌نویس',
            'slug' => 'draft-page',
            'content' => 'متن',
            'is_published' => false,
            'published_at' => null,
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertNotFound();
    }

    public function test_published_page_is_publicly_visible_and_raw_html_is_stripped(): void
    {
        $page = Page::query()->create([
            'title' => 'حریم خصوصی',
            'slug' => 'privacy-policy',
            'content' => "## داده‌های شما\n\nمتن امن\n\n<span data-page-raw-html=\"marker\">html-marker</span>",
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('حریم خصوصی')
            ->assertSee('داده‌های شما')
            ->assertSee('html-marker')
            ->assertDontSee('data-page-raw-html="marker"', false);
    }

    public function test_page_with_future_publish_time_is_not_publicly_visible(): void
    {
        $page = Page::query()->create([
            'title' => 'صفحه آینده',
            'slug' => 'future-page',
            'content' => 'متن',
            'is_published' => true,
            'published_at' => now()->addHour(),
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertNotFound();
    }
}
