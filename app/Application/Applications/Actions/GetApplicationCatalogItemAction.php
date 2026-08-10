<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\ApplicationCatalogItem;

final readonly class GetApplicationCatalogItemAction
{
    /**
     * @return array{
     *     id: int,
     *     slug: string,
     *     name: string,
     *     short_description: string,
     *     description: string|null,
     *     icon: string|null,
     * }
     */
    public function execute(
        ApplicationType $type,
    ): array {
        $item = ApplicationCatalogItem::query()
            ->published()
            ->where(
                'slug',
                $type->value,
            )
            ->firstOrFail();

        return [
            'id' => (int) $item->getKey(),
            'slug' => $type->value,
            'name' => $item->name,
            'short_description' => $item->short_description,
            'description' => $item->description,
            'icon' => $item->icon,
        ];
    }
}
