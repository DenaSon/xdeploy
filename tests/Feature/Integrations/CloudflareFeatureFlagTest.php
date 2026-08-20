<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Livewire\Integrations\Cloudflare\Overview;
use App\Livewire\Integrations\Cloudflare\Zones;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class CloudflareFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare_oauth.enabled' => false,
        ]);
    }

    public function test_disabled_cloudflare_routes_return_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.overview'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.zones'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.redirect'))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.callback'))
            ->assertNotFound();

        $this->actingAs($user)
            ->delete(route('panel.integrations.cloudflare.disconnect'))
            ->assertNotFound();
    }

    public function test_disabled_cloudflare_is_hidden_from_panel_navigation_and_integrations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('panel.integrations.index'))
            ->assertOk()
            ->assertDontSee('Cloudflare')
            ->assertSee('Telegram');
    }

    public function test_disabled_cloudflare_rejects_direct_livewire_mounts(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->assertStatus(404);

        Livewire::actingAs($user)
            ->test(Zones::class)
            ->assertStatus(404);
    }
}
