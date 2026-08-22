<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\DocumentationArticle;
use App\Models\Page;
use Illuminate\Http\Response;

final class SitemapController
{
    public function __invoke(): Response
    {
        $urls = [
            [
                'loc' => route('home'),
                'lastmod' => null,
            ],
            [
                'loc' => route('docs.index'),
                'lastmod' => null,
            ],
        ];

        $articles = DocumentationArticle::query()
            ->published()
            ->whereHas(
                'category',
                fn ($query) => $query->published(),
            )
            ->with('category')
            ->get();

        foreach ($articles as $article) {
            $urls[] = [
                'loc' => route('docs.show', [
                    $article->category->slug,
                    $article->slug,
                ]),
                'lastmod' => $article->updated_at?->toAtomString(),
            ];
        }

        $pages = Page::query()
            ->published()
            ->get();

        foreach ($pages as $page) {
            $urls[] = [
                'loc' => route('pages.show', $page->slug),
                'lastmod' => $page->updated_at?->toAtomString(),
            ];
        }

        return response()->view(
            'seo.sitemap',
            ['urls' => $urls],
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }
}
