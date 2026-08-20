<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Application\Applications\Actions\ListApplicationCatalogAction;
use App\Domain\Application\Shared\Enums\ApplicationType;
use Database\Seeders\ApplicationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApplicationCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeder_publishes_all_implemented_applications(): void
    {
        $this->seed(
            ApplicationCatalogSeeder::class,
        );

        $catalog = $this->app
            ->make(
                ListApplicationCatalogAction::class,
            )
            ->execute();

        $expectedSlugs = array_map(
            static fn (ApplicationType $type): string => $type->value,
            ApplicationType::cases(),
        );

        $actualSlugs = array_column(
            $catalog,
            'slug',
        );

        sort($expectedSlugs);
        sort($actualSlugs);

        $this->assertSame(
            $expectedSlugs,
            $actualSlugs,
        );

        $this->assertDatabaseHas(
            'applications',
            [
                'slug' => 'amneziawg',
                'name' => 'AmneziaWG',
                'is_published' => true,
                'sort_order' => 30,
            ],
        );

        $this->assertDatabaseHas(
            'applications',
            [
                'slug' => 'wordpress',
                'name' => 'WordPress',
                'is_published' => true,
                'sort_order' => 40,
            ],
        );
    }
}
