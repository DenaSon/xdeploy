<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\ApplicationCatalogItem;

final readonly class ListApplicationCatalogAction
{
    /**
     * Return only catalog items that are both published in persistence and
     * backed by an application capability implemented in code.
     *
     * @return array<int, array{
     *     id: int,
     *     slug: string,
     *     name: string,
     *     short_description: string,
     *     description: string|null,
     *     icon: string|null,
     * }>
     */
    public function execute(): array
    {
        $items = ApplicationCatalogItem::query()
            ->published()
            ->ordered()
            ->get();

        $applications = [];

        foreach ($items as $item) {
            $type = ApplicationType::tryFrom(
                $item->slug,
            );

            if ($type === null) {
                continue;
            }

            $applications[] = [
                'id' => (int) $item->getKey(),
                'slug' => $type->value,
                'name' => $item->name,
                'short_description' => $item->short_description,
                'description' => $item->description,
                'icon' => $item->icon,
            ];
        }

        return $applications;
    }
}
