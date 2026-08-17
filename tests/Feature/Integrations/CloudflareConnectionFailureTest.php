<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integration\Enums\IntegrationProvider;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareConnectionFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare_api.base_url' => 'https://api.cloudflare.com/client/v4',
            'services.cloudflare_api.connect_timeout' => 5,
            'services.cloudflare_api.timeout' => 15,
            'services.cloudflare_api.max_pages' => 20,
        ]);
    }

    public function test_cloudflare_page_survives_api_connection_timeout(): void
    {
        $user = User::factory()->create();

        IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'scopes' => [
                'account-settings.read',
                'zone.read',
                'dns.read',
                'dns.write',
                'offline_access',
            ],
            'access_token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        Http::fake(
            static fn () => throw new ConnectionException(
                'simulated timeout',
            ),
        );

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.overview'))
            ->assertOk()
            ->assertSee(
                'دریافت اطلاعات از Cloudflare ناموفق بود. دوباره تلاش کنید.',
            )
            ->assertDontSee('ConnectionException')
            ->assertDontSee('simulated timeout')
            ->assertDontSee('access-token');
    }
}
