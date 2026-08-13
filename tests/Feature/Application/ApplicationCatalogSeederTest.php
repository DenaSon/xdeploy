<?php

declare(strict_types=1);

namespace Tests\Feature\Application;

use App\Application\Applications\Actions\ListApplicationCatalogAction;
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

        $this->assertSame(
            [
                'marzban',
                'n8n',
                'amneziawg',
            ],
            array_column(
                $catalog,
                'slug',
            ),
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
    }
}
