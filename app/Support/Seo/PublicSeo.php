<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\DocumentationArticle;
use App\Models\Page;
use App\Settings\BrandingSettings;
use App\Settings\GeneralSettings;
use App\Settings\SeoSettings;
use Illuminate\Support\Str;

final class PublicSeo
{
    public function __construct(
        private readonly GeneralSettings $general,
        private readonly BrandingSettings $branding,
        private readonly SeoSettings $seo,
        private readonly SchemaBuilder $schema,
    ) {}

    public function generic(string $canonical): SeoData
    {
        return $this->make(
            title: $this->seo->default_title,
            description: $this->seo->default_description,
            canonical: $canonical,
        );
    }

    public function landing(): SeoData
    {
        $canonical = route('home');

        return $this->make(
            title: $this->seo->default_title,
            description: $this->seo->default_description,
            canonical: $canonical,
            schema: $this->schema->landing(
                siteName: $this->general->site_name,
                tagline: $this->branding->tagline,
                description: $this->seo->default_description,
                canonical: $canonical,
            ),
        );
    }

    public function documentationIndex(): SeoData
    {
        $canonical = route('docs.index');
        $description = 'راهنماها و مستندات رسمی '.$this->general->site_name.' برای اتصال سرور، راه‌اندازی سرویس‌ها و مدیریت زیرساخت.';

        return $this->make(
            title: 'مستندات | '.$this->general->site_name,
            description: $description,
            canonical: $canonical,
            schema: $this->schema->documentationIndex(
                siteName: $this->general->site_name,
                description: $description,
                canonical: $canonical,
                homeUrl: route('home'),
            ),
        );
    }

    public function documentationArticle(DocumentationArticle $article): SeoData
    {
        $canonical = route('docs.show', [
            $article->category->slug,
            $article->slug,
        ]);
        $description = $this->description(
            $article->excerpt ?: $article->content,
            $this->seo->default_description,
        );

        return $this->make(
            title: $article->title.' | '.$this->general->site_name,
            description: $description,
            canonical: $canonical,
            type: 'article',
            publishedTime: $article->published_at?->toIso8601String(),
            modifiedTime: $article->updated_at?->toIso8601String(),
            schema: $this->schema->documentationArticle(
                siteName: $this->general->site_name,
                article: $article,
                description: $description,
                canonical: $canonical,
                homeUrl: route('home'),
                docsUrl: route('docs.index'),
            ),
        );
    }

    public function page(Page $page): SeoData
    {
        $canonical = route('pages.show', $page->slug);
        $description = $this->description(
            $page->content,
            $this->seo->default_description,
        );

        return $this->make(
            title: $page->title.' | '.$this->general->site_name,
            description: $description,
            canonical: $canonical,
            publishedTime: $page->published_at?->toIso8601String(),
            modifiedTime: $page->updated_at?->toIso8601String(),
            schema: $this->schema->page(
                siteName: $this->general->site_name,
                page: $page,
                description: $description,
                canonical: $canonical,
                homeUrl: route('home'),
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function make(
        string $title,
        string $description,
        string $canonical,
        string $type = 'website',
        ?string $publishedTime = null,
        ?string $modifiedTime = null,
        array $schema = [],
    ): SeoData {
        return new SeoData(
            title: Str::limit(trim($title), 70, ''),
            description: Str::limit(trim($description), 160, ''),
            canonical: $canonical,
            robots: $this->seo->index_site
                ? 'index,follow,max-image-preview:large'
                : 'noindex,nofollow',
            type: $type,
            image: $this->absoluteImage($this->seo->default_og_image),
            publishedTime: $publishedTime,
            modifiedTime: $modifiedTime,
            schema: $schema,
            googleSiteVerification: $this->seo->google_site_verification,
            bingSiteVerification: $this->seo->bing_site_verification,
        );
    }

    private function description(?string $content, string $fallback): string
    {
        $content = trim((string) $content);

        if ($content === '') {
            return $fallback;
        }

        $html = Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $plain = html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return Str::limit(trim($plain), 160, '');
    }

    private function absoluteImage(?string $image): ?string
    {
        $image = trim((string) $image);

        if ($image === '') {
            return null;
        }

        if (filter_var($image, FILTER_VALIDATE_URL) !== false) {
            return $image;
        }

        return url('/'.ltrim($image, '/'));
    }
}
