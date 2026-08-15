<?php

declare(strict_types=1);

namespace App\Application\Navigation;

use App\Models\Page;
use Illuminate\Support\Facades\Cache;

final readonly class PublicFooterNavigation
{
    public const string CACHE_KEY = 'navigation.public.footer-pages.v1';

    private const int CACHE_TTL_SECONDS = 3600;

    /**
     * @return list<array{
     *     title: string,
     *     slug: string
     * }>
     */
    public function pages(): array
    {
        /** @var list<array{title: string, slug: string}> $pages */
        $pages = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            static function (): array {
                return Page::query()
                    ->published()
                    ->where('show_in_footer', true)
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->orderBy('id')
                    ->get([
                        'title',
                        'slug',
                    ])
                    ->map(
                        static fn (Page $page): array => [
                            'title' => $page->title,
                            'slug' => $page->slug,
                        ],
                    )
                    ->values()
                    ->all();
            },
        );

        return $pages;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
