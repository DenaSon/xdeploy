<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class PanelMobilePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_shell_places_the_mobile_drawer_above_the_header_and_uses_one_gutter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('panel.servers.index'))
            ->assertOk();

        $html = $response->getContent();

        self::assertStringContainsString(
            '[&_.drawer-side]:z-50',
            $html,
        );

        self::assertStringContainsString(
            'px-0 py-5',
            $html,
        );

        self::assertStringContainsString(
            'sm:px-5',
            $html,
        );
    }

    public function test_operating_system_value_is_rendered_as_isolated_ltr_technical_text(): void
    {
        $html = Blade::render(
            '<x-dashboard.server-overview :overview="$overview" />',
            [
                'overview' => [
                    'hostname' => 'cf-1',
                    'operatingSystem' => 'Ubuntu 26.04 LTS',
                    'kernel' => '7.0.0-22-generic',
                    'user' => 'ubuntu',
                    'privateIp' => '192.0.2.10',
                    'uptime' => 'up 1 minute',
                ],
            ],
        );

        self::assertMatchesRegularExpression(
            '/<p[^>]*class="[^"]*technical-value[^"]*"[^>]*dir="ltr"[^>]*title="Ubuntu 26\.04 LTS"/s',
            $html,
        );
    }
}
