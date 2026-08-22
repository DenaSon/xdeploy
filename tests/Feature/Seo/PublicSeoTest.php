<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use App\Models\Page;
use App\Settings\SeoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_outputs_canonical_social_and_structured_metadata(): void
    {
        $settings = app(SeoSettings::class);
        $settings->default_og_image = '/images/og/coreflare.png';
        $settings->google_site_verification = 'google-token';
        $settings->bing_site_verification = 'bing-token';
        $settings->save();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(
                '<link rel="canonical" href="'.route('home').'">',
                false,
            )
            ->assertSee('property="og:type" content="website"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('name="google-site-verification" content="google-token"', false)
            ->assertSee('name="msvalidate.01" content="bing-token"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"SoftwareApplication"', false)
            ->assertDontSee('<title>33187641</title>', false);
    }

    public function test_documentation_article_outputs_article_schema_and_canonical_url(): void
    {
        $category = $this->publishedCategory();
        $article = $this->publishedArticle($category, 'install-service');
        $url = route('docs.show', [$category->slug, $article->slug]);

        $this->get($url)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('property="og:type" content="article"', false)
            ->assertSee('property="article:published_time"', false)
            ->assertSee('"@type":"TechArticle"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_sitemap_contains_only_published_public_content(): void
    {
        $category = $this->publishedCategory();
        $publishedArticle = $this->publishedArticle($category, 'published-guide');
        $draftArticle = $category->articles()->create([
            'title' => 'Draft guide',
            'slug' => 'draft-guide',
            'excerpt' => null,
            'content' => 'Draft',
            'sort_order' => 20,
            'is_published' => false,
            'published_at' => null,
        ]);

        $publishedPage = Page::query()->create([
            'title' => 'Published page',
            'slug' => 'published-page',
            'content' => 'Published content',
            'is_published' => true,
            'published_at' => now()->subMinute(),
            'show_in_footer' => false,
            'sort_order' => 0,
        ]);
        $draftPage = Page::query()->create([
            'title' => 'Draft page',
            'slug' => 'draft-page',
            'content' => 'Draft content',
            'is_published' => false,
            'published_at' => null,
            'show_in_footer' => false,
            'sort_order' => 0,
        ]);

        $response = $this->get(route('sitemap'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('home'), false)
            ->assertSee(route('docs.index'), false)
            ->assertSee(
                route('docs.show', [$category->slug, $publishedArticle->slug]),
                false,
            )
            ->assertSee(route('pages.show', $publishedPage->slug), false)
            ->assertDontSee(
                route('docs.show', [$category->slug, $draftArticle->slug]),
                false,
            )
            ->assertDontSee(route('pages.show', $draftPage->slug), false);
    }

    public function test_robots_policy_follows_global_index_setting(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertSee('Allow: /')
            ->assertSee('Disallow: /admin/')
            ->assertSee('Sitemap: '.route('sitemap'));

        $settings = app(SeoSettings::class);
        $settings->index_site = false;
        $settings->save();

        $this->get(route('robots'))
            ->assertOk()
            ->assertSee('Disallow: /');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    public function test_authentication_pages_are_explicitly_noindex(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    private function publishedCategory(): DocumentationCategory
    {
        return DocumentationCategory::query()->create([
            'title' => 'Getting started',
            'slug' => 'getting-started',
            'description' => null,
            'sort_order' => 10,
            'is_published' => true,
        ]);
    }

    private function publishedArticle(
        DocumentationCategory $category,
        string $slug,
    ): DocumentationArticle {
        return $category->articles()->create([
            'title' => 'Guide '.$slug,
            'slug' => $slug,
            'excerpt' => 'A concise guide for testing SEO metadata.',
            'content' => '## Guide\n\nArticle body.',
            'sort_order' => 10,
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
    }
}
