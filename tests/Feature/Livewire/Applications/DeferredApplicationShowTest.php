<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Applications;

use App\Models\ApplicationCatalogItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeferredApplicationShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_application_show_request_renders_local_placeholder_before_ssh_inspection(): void
    {
        ApplicationCatalogItem::query()->create([
            'slug' => 'n8n',
            'name' => 'n8n',
            'short_description' => 'Workflow automation',
            'description' => 'Automation platform',
            'icon' => 'lucide.workflow',
            'is_published' => true,
            'sort_order' => 10,
        ]);

        $user = User::query()->create([
            'name' => 'Deferred Application Show Test',
            'phone' => '09120000031',
        ]);

        $server = $user->servers()->create([
            'name' => 'Deferred Application Show Server',
            'host' => '192.0.2.31',
            'port' => 22,
            'username' => 'root',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route(
                'panel.servers.applications.show',
                [
                    'server' => $server,
                    'application' => 'n8n',
                ],
            ));

        $response
            ->assertOk()
            ->assertSee('n8n')
            ->assertSee('Workflow automation')
            ->assertSee('در حال دریافت وضعیت برنامه')
            ->assertDontSee('wire:click="install"', false);
    }

    public function test_deferred_placeholder_does_not_expose_another_users_server(): void
    {
        ApplicationCatalogItem::query()->create([
            'slug' => 'n8n',
            'name' => 'n8n',
            'short_description' => 'Workflow automation',
            'description' => null,
            'icon' => 'lucide.workflow',
            'is_published' => true,
            'sort_order' => 10,
        ]);

        $owner = User::query()->create([
            'name' => 'Deferred Placeholder Owner',
            'phone' => '09120000032',
        ]);

        $attacker = User::query()->create([
            'name' => 'Deferred Placeholder Attacker',
            'phone' => '09120000033',
        ]);

        $server = $owner->servers()->create([
            'name' => 'Private Deferred Server',
            'host' => '192.0.2.32',
            'port' => 22,
            'username' => 'root',
        ]);

        $this
            ->actingAs($attacker)
            ->get(route(
                'panel.servers.applications.show',
                [
                    'server' => $server,
                    'application' => 'n8n',
                ],
            ))
            ->assertNotFound();
    }
}
