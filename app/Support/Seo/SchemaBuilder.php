<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\DocumentationArticle;
use App\Models\Page;

final class SchemaBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function landing(
        string $siteName,
        string $alternateName,
        string $tagline,
        string $description,
        string $canonical,
        string $logo,
    ): array {
        $organizationId = $canonical.'#organization';
        $websiteId = $canonical.'#website';
        $softwareId = $canonical.'#software';

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $organizationId,
                    'name' => $siteName,
                    'alternateName' => $alternateName,
                    'url' => $canonical,
                    'logo' => $logo,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $websiteId,
                    'url' => $canonical,
                    'name' => $siteName,
                    'alternateName' => $alternateName,
                    'inLanguage' => 'fa-IR',
                    'publisher' => ['@id' => $organizationId],
                ],
                [
                    '@type' => 'SoftwareApplication',
                    '@id' => $softwareId,
                    'name' => $siteName,
                    'url' => $canonical,
                    'description' => $description,
                    'applicationCategory' => 'DeveloperApplication',
                    'operatingSystem' => 'Web',
                    'slogan' => $tagline,
                    'publisher' => ['@id' => $organizationId],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function documentationIndex(
        string $siteName,
        string $description,
        string $canonical,
        string $homeUrl,
    ): array {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => $canonical.'#page',
                    'url' => $canonical,
                    'name' => 'مستندات '.$siteName,
                    'description' => $description,
                    'inLanguage' => 'fa-IR',
                ],
                $this->breadcrumbs([
                    ['name' => $siteName, 'url' => $homeUrl],
                    ['name' => 'مستندات', 'url' => $canonical],
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function documentationArticle(
        string $siteName,
        DocumentationArticle $article,
        string $description,
        string $canonical,
        string $homeUrl,
        string $docsUrl,
    ): array {
        $category = $article->category;

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'TechArticle',
                    '@id' => $canonical.'#article',
                    'headline' => $article->title,
                    'description' => $description,
                    'url' => $canonical,
                    'mainEntityOfPage' => $canonical,
                    'inLanguage' => 'fa-IR',
                    'datePublished' => $article->published_at?->toIso8601String(),
                    'dateModified' => $article->updated_at?->toIso8601String(),
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => $siteName,
                        'url' => $homeUrl,
                    ],
                ],
                $this->breadcrumbs([
                    ['name' => $siteName, 'url' => $homeUrl],
                    ['name' => 'مستندات', 'url' => $docsUrl],
                    [
                        'name' => $category->title,
                        'url' => $docsUrl.'#docs-category-'.$category->slug,
                    ],
                    ['name' => $article->title, 'url' => $canonical],
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function page(
        string $siteName,
        Page $page,
        string $description,
        string $canonical,
        string $homeUrl,
    ): array {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $canonical.'#page',
                    'url' => $canonical,
                    'name' => $page->title,
                    'description' => $description,
                    'inLanguage' => 'fa-IR',
                    'datePublished' => $page->published_at?->toIso8601String(),
                    'dateModified' => $page->updated_at?->toIso8601String(),
                ],
                $this->breadcrumbs([
                    ['name' => $siteName, 'url' => $homeUrl],
                    ['name' => $page->title, 'url' => $canonical],
                ]),
            ],
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ],
                $items,
                array_keys($items),
            ),
        ];
    }
}
