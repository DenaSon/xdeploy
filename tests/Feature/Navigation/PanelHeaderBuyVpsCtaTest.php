<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PanelHeaderBuyVpsCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_shows_buy_vps_cta_when_user_has_no_purchased_vps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('panel.servers.index'))
            ->assertOk()
            ->assertSeeHtml('data-panel-buy-vps-cta')
            ->assertSee(
                route('panel.servers.buy'),
                escape: false,
            );
    }

    public function test_header_hides_buy_vps_cta_when_user_has_active_unexpired_purchased_vps(): void
    {
        $user = User::factory()->create();

        $this->createCloudServer(
            user: $user,
            status: ServerStatus::Active,
            expiresAt: now()->addDay(),
        );

        $this->actingAs($user)
            ->get(route('panel.servers.index'))
            ->assertOk()
            ->assertDontSeeHtml('data-panel-buy-vps-cta');
    }

    public function test_header_shows_buy_vps_cta_when_all_purchased_vps_are_expired(): void
    {
        $user = User::factory()->create();

        $this->createCloudServer(
            user: $user,
            status: ServerStatus::Active,
            expiresAt: now()->subDay(),
        );

        $this->createCloudServer(
            user: $user,
            status: ServerStatus::Active,
            expiresAt: now()->subMinute(),
        );

        $this->actingAs($user)
            ->get(route('panel.servers.index'))
            ->assertOk()
            ->assertSeeHtml('data-panel-buy-vps-cta');
    }

    public function test_header_shows_buy_vps_cta_when_purchased_vps_is_inactive(): void
    {
        $user = User::factory()->create();

        $this->createCloudServer(
            user: $user,
            status: ServerStatus::Inactive,
            expiresAt: now()->addDay(),
        );

        $this->actingAs($user)
            ->get(route('panel.servers.index'))
            ->assertOk()
            ->assertSeeHtml('data-panel-buy-vps-cta');
    }

    public function test_active_user_provided_server_does_not_hide_buy_vps_cta(): void
    {
        $user = User::factory()->create();

        Server::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'External VPS',
            'host' => '192.0.2.10',
            'username' => 'root',
            'status' => ServerStatus::Active,
        ]);

        $this->actingAs($user)
            ->get(route('panel.servers.index'))
            ->assertOk()
            ->assertSeeHtml('data-panel-buy-vps-cta');
    }

    private function createCloudServer(
        User $user,
        ServerStatus $status,
        DateTimeInterface $expiresAt,
    ): Server {
        $cloudServerId = 'cta-test-'.Str::uuid()->toString();

        return Server::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'Cloud VPS',
            'username' => 'root',
            'status' => $status,
            'cloud_provider' => CloudProviderType::Arvan,
            'cloud_server_id' => $cloudServerId,
            'cloud_region' => 'iran',
            'provisioned_at' => now()->subDay(),
            'expires_at' => $expiresAt,
        ]);
    }
}
