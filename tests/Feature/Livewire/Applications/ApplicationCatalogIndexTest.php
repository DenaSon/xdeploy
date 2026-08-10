<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Applications;

use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\ApplicationCatalogItem;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ApplicationCatalogIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_index_renders_without_resolving_an_ssh_connection(): void
    {
        $user = $this->createUser(
            '09173432101',
        );

        $server = $this->createServer(
            $user,
        );

        $this->createCatalogItem();

        $this->app->bind(
            ConnectServerAction::class,
            static fn (): never => throw new LogicException(
                'Applications catalog must not resolve ConnectServerAction.',
            ),
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'panel.servers.applications.index',
                    [
                        'server' => $server,
                    ],
                ),
            )
            ->assertOk()
            ->assertSee('Marzban')
            ->assertSee('پنل مدیریت کاربران و سرویس‌های مبتنی بر Xray');
    }

    public function test_catalog_index_hides_unpublished_items(): void
    {
        $user = $this->createUser(
            '09173432102',
        );

        $server = $this->createServer(
            $user,
        );

        $this->createCatalogItem(
            isPublished: false,
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'panel.servers.applications.index',
                    [
                        'server' => $server,
                    ],
                ),
            )
            ->assertOk()
            ->assertDontSee('Marzban');
    }

    public function test_catalog_index_hides_database_rows_without_a_supported_application_capability(): void
    {
        $user = $this->createUser(
            '09173432103',
        );

        $server = $this->createServer(
            $user,
        );

        ApplicationCatalogItem::query()->create([
            'slug' => 'wordpress',
            'name' => 'WordPress',
            'short_description' => 'Unsupported catalog row for this test.',
            'description' => null,
            'icon' => 'lucide.globe',
            'is_published' => true,
            'sort_order' => 20,
        ]);

        $this
            ->actingAs($user)
            ->get(
                route(
                    'panel.servers.applications.index',
                    [
                        'server' => $server,
                    ],
                ),
            )
            ->assertOk()
            ->assertDontSee('WordPress');
    }

    public function test_catalog_index_rejects_a_foreign_server(): void
    {
        $user = $this->createUser(
            '09173432104',
        );

        $otherUser = $this->createUser(
            '09173432105',
        );

        $foreignServer = $this->createServer(
            $otherUser,
        );

        $this->createCatalogItem();

        $this
            ->actingAs($user)
            ->get(
                route(
                    'panel.servers.applications.index',
                    [
                        'server' => $foreignServer,
                    ],
                ),
            )
            ->assertNotFound();
    }

    private function createCatalogItem(
        bool $isPublished = true,
    ): ApplicationCatalogItem {
        return ApplicationCatalogItem::query()->create([
            'slug' => 'marzban',
            'name' => 'Marzban',
            'short_description' => 'پنل مدیریت کاربران و سرویس‌های مبتنی بر Xray',
            'description' => 'توضیحات برنامه Marzban',
            'icon' => 'lucide.shield-check',
            'is_published' => $isPublished,
            'sort_order' => 10,
        ]);
    }

    private function createUser(
        string $phone,
    ): User {
        return User::query()->create([
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
    ): Server {
        $server = new Server([
            'name' => 'catalog-test-server-'.$user->getKey(),
            'host' => '192.0.2.'.(10 + (int) $user->getKey()),
            'port' => 22,
            'username' => 'root',
        ]);

        $server->status = ServerStatus::Active;

        $user
            ->servers()
            ->save($server);

        return $server->refresh();
    }
}
