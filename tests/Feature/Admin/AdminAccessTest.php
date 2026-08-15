<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(
            route('admin.dashboard'),
        )->assertRedirect(
            route('login'),
        );
    }

    public function test_authenticated_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(
                route('admin.dashboard'),
            )
            ->assertForbidden();
    }

    public function test_admin_user_can_open_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(
                route('admin.dashboard'),
            )
            ->assertOk()
            ->assertSee('داشبورد مدیریت');
    }

    public function test_legacy_core_dashboard_route_is_removed(): void
    {
        $this->get(
            '/core/dashboard',
        )->assertNotFound();
    }
}
