<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Admin\AdminImpersonationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminUserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enter_customer_account(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.users.impersonate', $user));

        $response
            ->assertRedirect(route('panel.servers.index'))
            ->assertSessionHas(
                AdminImpersonationSession::SESSION_KEY,
                static fn (mixed $state): bool => is_array($state)
                    && ($state['admin_user_id'] ?? null) === $admin->id
                    && ($state['target_user_id'] ?? null) === $user->id,
            );

        $this->assertAuthenticatedAs($user);
    }

    public function test_non_admin_cannot_start_impersonation(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('admin.users.impersonate', $target))
            ->assertForbidden();
    }

    public function test_admin_cannot_impersonate_another_admin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $targetAdmin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this
            ->actingAs($admin)
            ->post(route('admin.users.impersonate', $targetAdmin))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_impersonated_user_can_return_to_original_admin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $user = User::factory()->create();

        $this
            ->actingAs($admin)
            ->post(route('admin.users.impersonate', $user))
            ->assertRedirect(route('panel.servers.index'));

        $this->assertAuthenticatedAs($user);

        $this
            ->post(route('panel.impersonation.stop'))
            ->assertRedirect(route('admin.users.show', $user));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(
            session()->has(AdminImpersonationSession::SESSION_KEY),
        );
    }

    public function test_stop_route_rejects_regular_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('panel.impersonation.stop'))
            ->assertForbidden();
    }
}
