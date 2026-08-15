<?php

declare(strict_types=1);

namespace App\Application\Navigation;

use App\Models\DocumentationCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final readonly class PublicDocumentationNavigation
{
    public const string CACHE_KEY = 'navigation.public.documentation-categories.v1';

    private const int CACHE_TTL_SECONDS = 3600;

    /**
     * @return list<array{
     *     title: string,
     *     slug: string,
     *     description: ?string
     * }>
     */
    public function categories(): array
    {
        /** @var list<array{title: string, slug: string, description: ?string}> $categories */
        $categories = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            static function (): array {
                return DocumentationCategory::query()
                    ->published()
                    ->whereHas(
                        'articles',
                        static fn (Builder $query) => $query->published(),
                    )
                    ->ordered()
                    ->get([
                        'title',
                        'slug',
                        'description',
                    ])
                    ->map(
                        static fn (DocumentationCategory $category): array => [
                            'title' => $category->title,
                            'slug' => $category->slug,
                            'description' => $category->description,
                        ],
                    )
                    ->values()
                    ->all();
            },
        );

        return $categories;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
